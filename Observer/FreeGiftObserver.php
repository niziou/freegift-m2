<?php
declare(strict_types=1);

namespace Niziou\FreeGift\Observer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Niziou\FreeGift\Model\Config\GiftConfig;
use Psr\Log\LoggerInterface;

class FreeGiftObserver implements ObserverInterface
{
    private const OPTION_CODE = 'niziou_freegift_item';
    private const GIFT_TYPE_CODE = 'niziou_freegift_type';
    private const GIFT_TYPE_RULE = 'rule_gift';
    private const GIFT_TYPE_BOGO = 'bogo_gift';
    private const BOGO_KEY_CODE = 'niziou_freegift_bogo_key';

    public function __construct(
        private readonly GiftConfig $giftConfig,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly RuleCollectionFactory $ruleCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Quote|null $quote */
        $quote = $observer->getEvent()->getQuote();
        if (!$quote instanceof Quote || $quote->getIsMultiShipping()) {
            return;
        }
        if ($quote->getData('niziou_freegift_processing')) {
            return;
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $appliedRuleIds = $this->getAppliedRuleIds($quote);
        $targetSku = $this->getSkuFromAppliedRules($appliedRuleIds);
        if (!$targetSku && $this->giftConfig->isEnabled($websiteId)) {
            $targetSku = $this->giftConfig->getConfiguredSku($websiteId);
        }
        $desiredBogoGifts = $this->getDesiredBogoGifts($quote, $appliedRuleIds);

        $changed = $this->removeObsoleteGiftItems($quote, $targetSku, $desiredBogoGifts);

        if ($targetSku) {
            $changed = $this->syncRuleGiftItem($quote, $targetSku) || $changed;
        }

        $changed = $this->syncBogoGiftItems($quote, $desiredBogoGifts) || $changed;
        $this->recollectTotalsWhenChanged($quote, $changed);
    }

    private function syncRuleGiftItem(Quote $quote, string $targetSku): bool
    {
        try {
            $product = $this->productRepository->get($targetSku, false, (int)$quote->getStoreId());
        } catch (\Throwable $exception) {
            $this->logger->warning(sprintf('FreeGift: unable to load gift product "%s".', $targetSku));
            return false;
        }

        if (!$this->canAddProductAsGift($product)) {
            $this->logger->warning(sprintf('FreeGift: product "%s" cannot be used as an automatic free gift.', $targetSku));
            return false;
        }

        $existingGiftItem = $this->findRuleGiftItem($quote, (string)$product->getSku());
        if ($existingGiftItem) {
            return $this->enforceGiftItemState($existingGiftItem, (string)$product->getSku(), 1, self::GIFT_TYPE_RULE);
        }

        try {
            $item = $quote->addProduct($product, 1);
            if (is_string($item)) {
                $this->logger->warning(sprintf('FreeGift: unable to add gift product "%s": %s', $targetSku, $item));
                return false;
            }

            if ($item instanceof QuoteItem) {
                $this->enforceGiftItemState($item, (string)$product->getSku(), 1, self::GIFT_TYPE_RULE);
                return true;
            }
        } catch (\Throwable $exception) {
            $this->logger->critical(sprintf('FreeGift: failed to add gift product "%s": %s', $targetSku, $exception->getMessage()));
        }

        return false;
    }

    /**
     * @return int[]
     */
    private function getAppliedRuleIds(Quote $quote): array
    {
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $appliedRuleIds = $address ? (string)$address->getAppliedRuleIds() : '';
        if ($appliedRuleIds === '') {
            $appliedRuleIds = (string)$quote->getAppliedRuleIds();
        }
        if ($appliedRuleIds === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', explode(',', $appliedRuleIds)))));
    }

    /**
     * @param int[] $appliedRuleIds
     */
    private function getSkuFromAppliedRules(array $appliedRuleIds): ?string
    {
        if ($appliedRuleIds === []) {
            return null;
        }

        $rules = $this->ruleCollectionFactory->create();
        $rules->addFieldToFilter('rule_id', ['in' => $appliedRuleIds]);
        $rules->addFieldToFilter('freegift_enabled', 1);
        $rules->setOrder('sort_order', 'ASC');

        foreach ($rules as $rule) {
            $sku = trim((string)$rule->getData('freegift_product_sku'));
            if ($sku !== '') {
                return $sku;
            }
        }

        return null;
    }

    /**
     * @param array<string, array{sku: string, qty: float, rule_id: int}> $desiredBogoGifts
     */
    private function removeObsoleteGiftItems(Quote $quote, ?string $targetSku, array $desiredBogoGifts): bool
    {
        $changed = false;

        foreach ($quote->getAllItems() as $item) {
            if (!$item instanceof QuoteItem || !$this->isGiftItem($item)) {
                continue;
            }

            if ($this->getGiftType($item) === self::GIFT_TYPE_BOGO) {
                $key = $this->getBogoKey($item);
                if ($key !== null && isset($desiredBogoGifts[$key])) {
                    continue;
                }
            } elseif ($targetSku && $item->getSku() === $targetSku) {
                continue;
            }

            $quote->removeItem((int)$item->getItemId());
            $changed = true;
        }

        return $changed;
    }

    private function findRuleGiftItem(Quote $quote, string $sku): ?QuoteItem
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item instanceof QuoteItem
                && $this->isGiftItem($item)
                && $this->getGiftType($item) !== self::GIFT_TYPE_BOGO
                && $item->getSku() === $sku
            ) {
                return $item;
            }
        }

        return null;
    }

    private function isGiftItem(QuoteItem $item): bool
    {
        return (bool)$item->getData(self::OPTION_CODE) || (bool)$item->getOptionByCode(self::OPTION_CODE);
    }

    private function getGiftType(QuoteItem $item): ?string
    {
        $value = (string)($item->getData(self::GIFT_TYPE_CODE) ?: $this->getItemOptionValue($item, self::GIFT_TYPE_CODE));

        return $value !== '' ? $value : null;
    }

    private function getBogoKey(QuoteItem $item): ?string
    {
        $value = (string)($item->getData(self::BOGO_KEY_CODE) ?: $this->getItemOptionValue($item, self::BOGO_KEY_CODE));

        return $value !== '' ? $value : null;
    }

    private function enforceGiftItemState(
        QuoteItem $item,
        string $sku,
        float $qty,
        string $giftType,
        ?string $bogoKey = null
    ): bool {
        $changed = $item->getCustomPrice() === null
            || $item->getOriginalCustomPrice() === null
            || (float)$item->getCustomPrice() !== 0.0
            || (float)$item->getOriginalCustomPrice() !== 0.0
            || (float)$item->getQty() !== $qty
            || !$item->getNoDiscount()
            || !$item->getOptionByCode(self::OPTION_CODE)
            || !$item->getOptionByCode(self::GIFT_TYPE_CODE)
            || ($bogoKey !== null && $this->getBogoKey($item) !== $bogoKey);

        $item->setData(self::OPTION_CODE, true);
        $item->setData(self::GIFT_TYPE_CODE, $giftType);
        $item->setData('niziou_freegift_sku', $sku);
        $item->setData('gift_message_available', false);
        $item->setNoDiscount(true);
        $item->setQty($qty);
        $item->setCustomPrice(0.0);
        $item->setOriginalCustomPrice(0.0);
        $item->getProduct()->setIsSuperMode(true);

        $this->setItemOption($item, self::OPTION_CODE, '1');
        $this->setItemOption($item, self::GIFT_TYPE_CODE, $giftType);
        if ($bogoKey !== null) {
            $item->setData(self::BOGO_KEY_CODE, $bogoKey);
            $this->setItemOption($item, self::BOGO_KEY_CODE, $bogoKey);
        }

        return $changed;
    }

    /**
     * @param int[] $appliedRuleIds
     * @return array<string, array{sku: string, qty: float, rule_id: int}>
     */
    private function getDesiredBogoGifts(Quote $quote, array $appliedRuleIds): array
    {
        if ($appliedRuleIds === []) {
            return [];
        }

        $rules = $this->ruleCollectionFactory->create();
        $rules->addFieldToFilter('rule_id', ['in' => $appliedRuleIds]);
        $rules->addFieldToFilter('freegift_bogo_enabled', 1);
        $rules->setOrder('sort_order', 'ASC');

        $desired = [];
        foreach ($rules as $rule) {
            $ruleId = (int)$rule->getId();
            $buyQty = max(1, (int)$rule->getData('freegift_bogo_buy_qty'));
            $freeQty = max(1, (int)$rule->getData('freegift_bogo_free_qty'));
            $maxFreeQty = (int)$rule->getData('freegift_bogo_max_free_qty');
            $giftSku = trim((string)$rule->getData('freegift_bogo_product_sku'));

            foreach ($quote->getAllVisibleItems() as $item) {
                if (!$item instanceof QuoteItem || $this->isGiftItem($item) || !$this->itemHasAppliedRule($item, $ruleId)) {
                    continue;
                }

                $qty = floor((float)$item->getQty() / $buyQty) * $freeQty;
                if ($maxFreeQty > 0) {
                    $qty = min($qty, $maxFreeQty);
                }
                if ($qty <= 0) {
                    continue;
                }

                $paidSku = (string)$item->getSku();
                $targetSku = $giftSku !== '' ? $giftSku : $paidSku;
                $key = $this->buildBogoKey($ruleId, $paidSku, $targetSku);
                $desired[$key] = [
                    'sku' => $targetSku,
                    'qty' => (float)$qty,
                    'rule_id' => $ruleId,
                ];
            }
        }

        return $desired;
    }

    /**
     * @param array<string, array{sku: string, qty: float, rule_id: int}> $desiredBogoGifts
     */
    private function syncBogoGiftItems(Quote $quote, array $desiredBogoGifts): bool
    {
        $changed = false;

        foreach ($desiredBogoGifts as $key => $gift) {
            $existingGiftItem = $this->findBogoGiftItem($quote, $key);
            if ($existingGiftItem) {
                $changed = $this->enforceGiftItemState(
                    $existingGiftItem,
                    $gift['sku'],
                    $gift['qty'],
                    self::GIFT_TYPE_BOGO,
                    $key
                ) || $changed;
                continue;
            }

            try {
                $product = $this->productRepository->get($gift['sku'], false, (int)$quote->getStoreId());
            } catch (\Throwable $exception) {
                $this->logger->warning(sprintf('FreeGift: unable to load BOGO gift product "%s".', $gift['sku']));
                continue;
            }

            if (!$this->canAddProductAsGift($product)) {
                $this->logger->warning(sprintf('FreeGift: product "%s" cannot be used as an automatic BOGO gift.', $gift['sku']));
                continue;
            }

            $product->addCustomOption(self::OPTION_CODE, '1');
            $product->addCustomOption(self::GIFT_TYPE_CODE, self::GIFT_TYPE_BOGO);
            $product->addCustomOption(self::BOGO_KEY_CODE, $key);

            try {
                $item = $quote->addProduct($product, new DataObject([
                    'qty' => $gift['qty'],
                    self::OPTION_CODE => 1,
                    self::GIFT_TYPE_CODE => self::GIFT_TYPE_BOGO,
                    self::BOGO_KEY_CODE => $key,
                ]));
                if (is_string($item)) {
                    $this->logger->warning(sprintf('FreeGift: unable to add BOGO gift product "%s": %s', $gift['sku'], $item));
                    continue;
                }

                if ($item instanceof QuoteItem) {
                    $this->enforceGiftItemState($item, $gift['sku'], $gift['qty'], self::GIFT_TYPE_BOGO, $key);
                    $changed = true;
                }
            } catch (\Throwable $exception) {
                $this->logger->critical(sprintf('FreeGift: failed to add BOGO gift product "%s": %s', $gift['sku'], $exception->getMessage()));
            }
        }

        return $changed;
    }

    private function findBogoGiftItem(Quote $quote, string $key): ?QuoteItem
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item instanceof QuoteItem
                && $this->isGiftItem($item)
                && $this->getGiftType($item) === self::GIFT_TYPE_BOGO
                && $this->getBogoKey($item) === $key
            ) {
                return $item;
            }
        }

        return null;
    }

    private function itemHasAppliedRule(QuoteItem $item, int $ruleId): bool
    {
        $appliedRuleIds = (string)$item->getAppliedRuleIds();
        if ($appliedRuleIds === '') {
            return false;
        }

        return in_array($ruleId, array_map('intval', explode(',', $appliedRuleIds)), true);
    }

    private function buildBogoKey(int $ruleId, string $paidSku, string $giftSku): string
    {
        return $ruleId . ':' . $paidSku . ':' . $giftSku;
    }

    private function setItemOption(QuoteItem $item, string $code, string $value): void
    {
        $option = $item->getOptionByCode($code);
        if ($option) {
            $option->setValue($value);
            return;
        }

        $item->addOption([
            'code' => $code,
            'value' => $value,
            'product_id' => $item->getProductId(),
        ]);
    }

    private function getItemOptionValue(QuoteItem $item, string $code): ?string
    {
        $option = $item->getOptionByCode($code);

        return $option ? (string)$option->getValue() : null;
    }

    private function canAddProductAsGift(ProductInterface $product): bool
    {
        $typeId = (string)$product->getTypeId();
        if (!in_array($typeId, [ProductType::TYPE_SIMPLE, ProductType::TYPE_VIRTUAL], true)) {
            return false;
        }

        if ($product->getTypeInstance()->hasRequiredOptions($product)) {
            return false;
        }

        return (bool)$product->isAvailable();
    }

    private function recollectTotalsWhenChanged(Quote $quote, bool $changed): void
    {
        if (!$changed) {
            return;
        }

        $quote->setData('niziou_freegift_processing', true);
        try {
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
        } finally {
            $quote->unsetData('niziou_freegift_processing');
        }
    }
}
