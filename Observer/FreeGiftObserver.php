<?php
declare(strict_types=1);

namespace Niziou\FreeGift\Observer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
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
        $targetSku = $this->getSkuFromAppliedRules($quote);
        if (!$targetSku && $this->giftConfig->isEnabled($websiteId)) {
            $targetSku = $this->giftConfig->getConfiguredSku($websiteId);
        }

        $changed = $this->removeObsoleteGiftItems($quote, $targetSku);

        if (!$targetSku) {
            $this->recollectTotalsWhenChanged($quote, $changed);
            return;
        }

        try {
            $product = $this->productRepository->get($targetSku, false, (int)$quote->getStoreId());
        } catch (\Throwable $exception) {
            $this->logger->warning(sprintf('FreeGift: unable to load gift product "%s".', $targetSku));
            $this->recollectTotalsWhenChanged($quote, $changed);
            return;
        }

        if (!$this->canAddProductAsGift($product)) {
            $this->logger->warning(sprintf('FreeGift: product "%s" cannot be used as an automatic free gift.', $targetSku));
            $this->recollectTotalsWhenChanged($quote, $changed);
            return;
        }

        $existingGiftItem = $this->findGiftItem($quote, (string)$product->getSku());
        if ($existingGiftItem) {
            $changed = $this->enforceGiftItemState($existingGiftItem, (string)$product->getSku()) || $changed;
            $this->recollectTotalsWhenChanged($quote, $changed);
            return;
        }

        try {
            $item = $quote->addProduct($product, 1);
            if (is_string($item)) {
                $this->logger->warning(sprintf('FreeGift: unable to add gift product "%s": %s', $targetSku, $item));
                $this->recollectTotalsWhenChanged($quote, $changed);
                return;
            }

            if ($item instanceof QuoteItem) {
                $this->enforceGiftItemState($item, (string)$product->getSku());
                $changed = true;
            }
        } catch (\Throwable $exception) {
            $this->logger->critical(sprintf('FreeGift: failed to add gift product "%s": %s', $targetSku, $exception->getMessage()));
        }

        $this->recollectTotalsWhenChanged($quote, $changed);
    }

    private function getSkuFromAppliedRules(Quote $quote): ?string
    {
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $appliedRuleIds = $address ? (string)$address->getAppliedRuleIds() : '';
        if ($appliedRuleIds === '') {
            $appliedRuleIds = (string)$quote->getAppliedRuleIds();
        }
        if ($appliedRuleIds === '') {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $appliedRuleIds)))));
        if ($ids === []) {
            return null;
        }

        $rules = $this->ruleCollectionFactory->create();
        $rules->addFieldToFilter('rule_id', ['in' => $ids]);
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

    private function removeObsoleteGiftItems(Quote $quote, ?string $targetSku): bool
    {
        $changed = false;

        foreach ($quote->getAllItems() as $item) {
            if (!$item instanceof QuoteItem || !$this->isGiftItem($item)) {
                continue;
            }

            if ($targetSku && $item->getSku() === $targetSku) {
                continue;
            }

            $quote->removeItem((int)$item->getItemId());
            $changed = true;
        }

        return $changed;
    }

    private function findGiftItem(Quote $quote, string $sku): ?QuoteItem
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item instanceof QuoteItem && $this->isGiftItem($item) && $item->getSku() === $sku) {
                return $item;
            }
        }

        return null;
    }

    private function isGiftItem(QuoteItem $item): bool
    {
        return (bool)$item->getData(self::OPTION_CODE) || (bool)$item->getOptionByCode(self::OPTION_CODE);
    }

    private function enforceGiftItemState(QuoteItem $item, string $sku): bool
    {
        $changed = $item->getCustomPrice() === null
            || $item->getOriginalCustomPrice() === null
            || (float)$item->getCustomPrice() !== 0.0
            || (float)$item->getOriginalCustomPrice() !== 0.0
            || (float)$item->getQty() !== 1.0
            || !$item->getNoDiscount()
            || !$item->getOptionByCode(self::OPTION_CODE);

        $item->setData(self::OPTION_CODE, true);
        $item->setData('niziou_freegift_sku', $sku);
        $item->setData('gift_message_available', false);
        $item->setNoDiscount(true);
        $item->setQty(1);
        $item->setCustomPrice(0.0);
        $item->setOriginalCustomPrice(0.0);
        $item->getProduct()->setIsSuperMode(true);

        if (!$item->getOptionByCode(self::OPTION_CODE)) {
            $item->addOption([
                'code' => self::OPTION_CODE,
                'value' => '1',
                'product_id' => $item->getProductId(),
            ]);
        }

        return $changed;
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
