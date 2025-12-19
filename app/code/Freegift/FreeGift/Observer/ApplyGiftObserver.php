<?php
namespace Freegift\FreeGift\Observer;

use Freegift\FreeGift\Model\Config\GiftConfig;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Psr\Log\LoggerInterface;

class ApplyGiftObserver implements ObserverInterface
{
    public function __construct(
        private readonly GiftConfig $giftConfig,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly RuleCollectionFactory $ruleCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        if (!$quote || $quote->getIsMultiShipping()) {
            return;
        }

        $websiteId = $quote->getStore()->getWebsiteId();
        $activeRuleSku = $this->getSkuFromAppliedRules($quote);
        $globalSku = $this->giftConfig->isEnabled($websiteId) ? $this->giftConfig->getConfiguredSku($websiteId) : null;

        $targetSku = $activeRuleSku ?: $globalSku;

        $this->removeObsoleteGiftItems($quote, $targetSku);

        if (!$targetSku) {
            return;
        }

        try {
            $product = $this->productRepository->get($targetSku, false, $quote->getStore()->getId());
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('FreeGift: unable to load product with SKU %s', $targetSku));
            return;
        }

        if (!$this->isGiftProductAllowed($product->getTypeId())) {
            $this->logger->warning(sprintf('FreeGift: product %s is not an allowed type for free gifts', $targetSku));
            return;
        }

        if ($product->getTypeInstance()->hasRequiredOptions($product)) {
            $this->logger->warning(sprintf('FreeGift: product %s has required options and cannot be auto-added', $targetSku));
            return;
        }

        if (!$product->isAvailable()) {
            return;
        }

        $existingGiftItem = $this->findGiftItem($quote, $product->getSku());
        if ($existingGiftItem) {
            $this->enforceGiftPricing($existingGiftItem);
            return;
        }

        try {
            $item = $quote->addProduct($product, 1);
            if (is_string($item)) {
                $this->logger->warning(sprintf('FreeGift: unable to add gift product %s: %s', $targetSku, $item));
                return;
            }
            $this->flagGiftItem($item, $product->getSku());
            $this->enforceGiftPricing($item);
        } catch (\Exception $e) {
            $this->logger->critical(sprintf('FreeGift: failed to add gift product %s: %s', $targetSku, $e->getMessage()));
        }
    }

    private function getSkuFromAppliedRules(Quote $quote): ?string
    {
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $appliedRuleIds = $address ? $address->getAppliedRuleIds() : '';
        if (!$appliedRuleIds) {
            $appliedRuleIds = $quote->getAppliedRuleIds();
        }
        if (!$appliedRuleIds) {
            return null;
        }

        $ids = array_filter(array_map('intval', explode(',', $appliedRuleIds)));
        if (empty($ids)) {
            return null;
        }

        $rules = $this->ruleCollectionFactory->create();
        $rules->addFieldToFilter('rule_id', ['in' => $ids]);
        $rules->addFieldToFilter('freegift_enabled', 1);
        $rules->setOrder('sort_order', 'ASC');

        foreach ($rules as $rule) {
            $sku = (string)$rule->getData('freegift_product_sku');
            if (trim($sku) !== '') {
                return $sku;
            }
        }

        return null;
    }

    private function removeObsoleteGiftItems(Quote $quote, ?string $targetSku): void
    {
        foreach ($quote->getAllItems() as $item) {
            if (!$this->isGiftItem($item)) {
                continue;
            }
            if ($targetSku && $item->getSku() === $targetSku) {
                continue;
            }
            $quote->removeItem($item->getItemId());
        }
    }

    private function findGiftItem(Quote $quote, string $sku): ?QuoteItem
    {
        foreach ($quote->getAllItems() as $item) {
            if ($this->isGiftItem($item) && $item->getSku() === $sku) {
                return $item;
            }
        }

        return null;
    }

    private function isGiftItem(QuoteItem $item): bool
    {
        return (bool)$item->getData('freegift_item') || (bool)$item->getOptionByCode('freegift_item');
    }

    private function flagGiftItem(QuoteItem $item, string $sku): void
    {
        $item->setData('freegift_item', true);
        $item->setData('gift_message_available', false);
        $item->setNoDiscount(true);
        $item->setQty(1);
        $item->setData('freegift_sku', $sku);
    }

    private function enforceGiftPricing(QuoteItem $item): void
    {
        $item->setCustomPrice(0);
        $item->setOriginalCustomPrice(0);
        $item->getProduct()->setIsSuperMode(true);
        $item->setQty(1);
    }

    private function isGiftProductAllowed(string $typeId): bool
    {
        return in_array($typeId, [ProductType::TYPE_SIMPLE, ProductType::TYPE_VIRTUAL], true);
    }
}
