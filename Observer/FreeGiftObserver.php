<?php
declare(strict_types=1);

namespace Niziou\FreeGift\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Niziou\FreeGift\Service\FreeGiftRuleRetriver;
use Psr\Log\LoggerInterface;

class FreeGiftObserver implements ObserverInterface
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param FreeGiftRuleRetriver $freeGiftRuleRetriver
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected FreeGiftRuleRetriver $freeGiftRuleRetriver,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * Execute observer method.
     *
     * This method calculates the cart subtotal excluding any free gift items,
     * finds an applicable free gift rule (if any), and then adds or removes the free gift.
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        /** @var Quote $quote */
        $quote = $observer->getQuote();
        if (!$quote) {
            return;
        }

        try {
            // Calculate subtotal excluding free gift items.
            $subtotal = 0;
            foreach ($quote->getAllVisibleItems() as $item) {
                $isFreeGift = false;
                foreach ($item->getOptions() as $option) {
                    if ($option->getCode() == 'is_freegift' && $option->getValue() == 1) {
                        $isFreeGift = true;
                        break;
                    }
                }
                if (!$isFreeGift) {
                    $subtotal += $item->getRowTotal();
                }
            }

            // Retrieve free gift rules from the helper.
            $rules = $this->freeGiftHelper->getFreeGiftRules();
            $applicableRule = null;

            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    if (isset($rule['threshold']) && isset($rule['gift_product_sku'])) {
                        if ($subtotal >= (float)$rule['threshold']) {
                            // If multiple rules match, choose the one with the highest threshold.
                            if (!$applicableRule || (float)$rule['threshold'] > (float)$applicableRule['threshold']) {
                                $applicableRule = $rule;
                            }
                        }
                    }
                }
            }

            // Look for an existing free gift in the quote.
            $freeGiftItem = null;
            foreach ($quote->getAllItems() as $item) {
                foreach ($item->getOptions() as $option) {
                    if ($option->getCode() == 'is_freegift' && $option->getValue() == 1) {
                        $freeGiftItem = $item;
                        break 2;
                    }
                }
            }

            if ($applicableRule) {
                // There is an applicable rule.
                if ($freeGiftItem) {
                    // If the free gift in the cart does not match the rule SKU, remove it.
                    if ($freeGiftItem->getSku() != $applicableRule['gift_product_sku']) {
                        $quote->removeItem($freeGiftItem->getId());
                        $freeGiftItem = null;
                    }
                }
                if (!$freeGiftItem) {
                    // Load the product by SKU and add it as the free gift.
                    $product = $this->productRepository->get($applicableRule['gift_product_sku']);
                    if ($product && $product->getId()) {
                        $freeGiftItem = $quote->addProduct($product, 1);
                        // Set the price to 0.01.
                        $freeGiftItem->setCustomPrice(0.01);
                        $freeGiftItem->setOriginalCustomPrice(0.01);
                        $freeGiftItem->getProduct()->setIsSuperMode(true);
                        // Mark the item as a free gift.
                        $freeGiftItem->addOption([
                            'code'  => 'is_freegift',
                            'value' => 1
                        ]);
                        // Optionally save the actual gift value for display purposes.
                        if (isset($applicableRule['gift_actual_price'])) {
                            $freeGiftItem->addOption([
                                'code'  => 'freegift_actual_price',
                                'value' => $applicableRule['gift_actual_price']
                            ]);
                        }
                    }
                }
            } else {
                // No applicable rule: if a free gift exists, remove it.
                if ($freeGiftItem) {
                    $quote->removeItem($freeGiftItem->getId());
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('FreeGiftObserver error: ' . $e->getMessage());
        }
    }
}
