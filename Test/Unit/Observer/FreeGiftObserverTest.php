<?php
declare(strict_types=1);

namespace Magento\Catalog\Api\Data {
    interface ProductInterface
    {
    }
}

namespace Magento\Catalog\Api {
    interface ProductRepositoryInterface
    {
    }
}

namespace Magento\Catalog\Model\Product {
    class Type
    {
        public const TYPE_SIMPLE = 'simple';
        public const TYPE_VIRTUAL = 'virtual';
    }
}

namespace Magento\Framework {
    class DataObject
    {
        public function __construct(private array $data = [])
        {
        }

        public function getData(?string $key = null): mixed
        {
            return $key === null ? $this->data : ($this->data[$key] ?? null);
        }
    }
}

namespace Magento\Framework\Event {
    class Observer
    {
        public function __construct(private object $event)
        {
        }

        public function getEvent(): object
        {
            return $this->event;
        }
    }

    interface ObserverInterface
    {
    }
}

namespace Magento\Quote\Model {
    class Quote
    {
        private array $data = [];
        private bool $totalsCollectedFlag = true;
        private int $nextItemId = 100;

        public function __construct(
            private object $store,
            private object $shippingAddress,
            private array $items = []
        ) {
        }

        public function getIsMultiShipping(): bool
        {
            return false;
        }

        public function getData(string $key): mixed
        {
            return $this->data[$key] ?? null;
        }

        public function setData(string $key, mixed $value): self
        {
            $this->data[$key] = $value;
            return $this;
        }

        public function unsetData(string $key): self
        {
            unset($this->data[$key]);
            return $this;
        }

        public function getStore(): object
        {
            return $this->store;
        }

        public function getStoreId(): int
        {
            return $this->store->getId();
        }

        public function isVirtual(): bool
        {
            return false;
        }

        public function getShippingAddress(): object
        {
            return $this->shippingAddress;
        }

        public function getBillingAddress(): object
        {
            return $this->shippingAddress;
        }

        public function getAppliedRuleIds(): string
        {
            return '';
        }

        public function getAllItems(): array
        {
            return $this->items;
        }

        public function getAllVisibleItems(): array
        {
            return array_filter($this->items, static fn (object $item): bool => !$item->isDeleted());
        }

        public function addProduct(object $product, mixed $request): object|string
        {
            $qty = is_object($request) && method_exists($request, 'getData') ? (float)$request->getData('qty') : (float)$request;
            $item = new \Magento\Quote\Model\Quote\Item($product->getSku(), $qty, '');
            $item->setProduct($product);
            $item->setProductId(++$this->nextItemId);
            $item->setItemId($this->nextItemId);
            $this->items[] = $item;

            return $item;
        }

        public function removeItem(int $itemId): void
        {
            foreach ($this->items as $item) {
                if ($item->getItemId() === $itemId) {
                    $item->delete();
                }
            }
        }

        public function setTotalsCollectedFlag(bool $flag): self
        {
            $this->totalsCollectedFlag = $flag;
            return $this;
        }

        public function collectTotals(): void
        {
        }
    }
}

namespace Magento\Quote\Model\Quote {
    class Item
    {
        private array $data = [];
        private array $options = [];
        private mixed $customPrice = null;
        private mixed $originalCustomPrice = null;
        private bool $noDiscount = false;
        private int $itemId = 0;
        private int $productId = 0;
        private bool $deleted = false;
        private object $product;

        public function __construct(
            private string $sku,
            private float $qty,
            private string $appliedRuleIds
        ) {
        }

        public function getSku(): string
        {
            return $this->sku;
        }

        public function getQty(): float
        {
            return $this->qty;
        }

        public function setQty(float $qty): self
        {
            $this->qty = $qty;
            return $this;
        }

        public function getAppliedRuleIds(): string
        {
            return $this->appliedRuleIds;
        }

        public function getData(string $key): mixed
        {
            return $this->data[$key] ?? null;
        }

        public function setData(string $key, mixed $value): self
        {
            $this->data[$key] = $value;
            return $this;
        }

        public function getOptionByCode(string $code): ?object
        {
            return $this->options[$code] ?? null;
        }

        public function addOption(array $option): void
        {
            $this->options[$option['code']] = new class((string)$option['value']) {
                public function __construct(private string $value)
                {
                }

                public function getValue(): string
                {
                    return $this->value;
                }

                public function setValue(string $value): void
                {
                    $this->value = $value;
                }
            };
        }

        public function getCustomPrice(): mixed
        {
            return $this->customPrice;
        }

        public function setCustomPrice(float $price): void
        {
            $this->customPrice = $price;
        }

        public function getOriginalCustomPrice(): mixed
        {
            return $this->originalCustomPrice;
        }

        public function setOriginalCustomPrice(float $price): void
        {
            $this->originalCustomPrice = $price;
        }

        public function getNoDiscount(): bool
        {
            return $this->noDiscount;
        }

        public function setNoDiscount(bool $noDiscount): void
        {
            $this->noDiscount = $noDiscount;
        }

        public function setProduct(object $product): void
        {
            $this->product = $product;
        }

        public function getProduct(): object
        {
            return $this->product;
        }

        public function setProductId(int $productId): void
        {
            $this->productId = $productId;
        }

        public function getProductId(): int
        {
            return $this->productId;
        }

        public function setItemId(int $itemId): void
        {
            $this->itemId = $itemId;
        }

        public function getItemId(): int
        {
            return $this->itemId;
        }

        public function delete(): void
        {
            $this->deleted = true;
        }

        public function isDeleted(): bool
        {
            return $this->deleted;
        }
    }
}

namespace Magento\SalesRule\Model\ResourceModel\Rule {
    class CollectionFactory
    {
    }
}

namespace Psr\Log {
    interface LoggerInterface
    {
    }
}

namespace Niziou\FreeGift\Test\Unit\Observer {
    use Magento\Catalog\Api\Data\ProductInterface;
    use Magento\Catalog\Api\ProductRepositoryInterface;
    use Magento\Framework\Event\Observer;
    use Magento\Quote\Model\Quote;
    use Magento\Quote\Model\Quote\Item as QuoteItem;
    use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
    use Niziou\FreeGift\Model\Config\GiftConfig;
    use Niziou\FreeGift\Observer\FreeGiftObserver;
    use PHPUnit\Framework\TestCase;
    use Psr\Log\LoggerInterface;

    class FreeGiftObserverTest extends TestCase
    {
        public function testBogoAddsSameSkuGiftWhenGiftSkuIsEmpty(): void
        {
            $paidItem = $this->paidItem('SKU-A', 3, '10');
            $quote = $this->quote([$paidItem], '10');
            $observer = $this->observer([
                $this->rule(10, [
                    'freegift_bogo_enabled' => 1,
                    'freegift_bogo_buy_qty' => 1,
                    'freegift_bogo_free_qty' => 1,
                    'freegift_bogo_product_sku' => '',
                ]),
            ]);

            $observer->execute(new Observer(new class($quote) {
                public function __construct(private Quote $quote)
                {
                }

                public function getQuote(): Quote
                {
                    return $this->quote;
                }
            }));

            $giftItem = $this->findGiftItem($quote, 'SKU-A');
            self::assertNotNull($giftItem);
            self::assertSame(3.0, $giftItem->getQty());
            self::assertSame(0.0, $giftItem->getCustomPrice());
            self::assertTrue($giftItem->getNoDiscount());
            self::assertSame('bogo_gift', $giftItem->getOptionByCode('niziou_freegift_type')->getValue());
            self::assertSame('10:SKU-A:SKU-A', $giftItem->getOptionByCode('niziou_freegift_bogo_key')->getValue());
        }

        public function testBogoAddsConfiguredGiftSkuAndCalculatesQtyFromPaidItem(): void
        {
            $paidItem = $this->paidItem('SKU-A', 5, '10');
            $quote = $this->quote([$paidItem], '10');
            $observer = $this->observer([
                $this->rule(10, [
                    'freegift_bogo_enabled' => 1,
                    'freegift_bogo_buy_qty' => 2,
                    'freegift_bogo_free_qty' => 1,
                    'freegift_bogo_product_sku' => 'SKU-B',
                ]),
            ]);

            $observer->execute(new Observer(new class($quote) {
                public function __construct(private Quote $quote)
                {
                }

                public function getQuote(): Quote
                {
                    return $this->quote;
                }
            }));

            $giftItem = $this->findGiftItem($quote, 'SKU-B');
            self::assertNotNull($giftItem);
            self::assertSame(2.0, $giftItem->getQty());
            self::assertSame('10:SKU-A:SKU-B', $giftItem->getOptionByCode('niziou_freegift_bogo_key')->getValue());
        }

        public function testBogoRespectsMaximumFreeQty(): void
        {
            $paidItem = $this->paidItem('SKU-A', 10, '10');
            $quote = $this->quote([$paidItem], '10');
            $observer = $this->observer([
                $this->rule(10, [
                    'freegift_bogo_enabled' => 1,
                    'freegift_bogo_buy_qty' => 1,
                    'freegift_bogo_free_qty' => 1,
                    'freegift_bogo_max_free_qty' => 2,
                    'freegift_bogo_product_sku' => 'SKU-B',
                ]),
            ]);

            $observer->execute(new Observer(new class($quote) {
                public function __construct(private Quote $quote)
                {
                }

                public function getQuote(): Quote
                {
                    return $this->quote;
                }
            }));

            self::assertSame(2.0, $this->findGiftItem($quote, 'SKU-B')->getQty());
        }

        private function observer(array $rules): FreeGiftObserver
        {
            return new FreeGiftObserver(
                $this->createStub(GiftConfig::class),
                new class implements ProductRepositoryInterface {
                    public function get(string $sku, bool $editMode = false, ?int $storeId = null): ProductInterface
                    {
                        return new class($sku) implements ProductInterface {
                            public function __construct(private string $sku)
                            {
                            }

                            public function getSku(): string
                            {
                                return $this->sku;
                            }

                            public function getTypeId(): string
                            {
                                return 'simple';
                            }

                            public function isAvailable(): bool
                            {
                                return true;
                            }

                            public function getTypeInstance(): object
                            {
                                return new class {
                                    public function hasRequiredOptions(ProductInterface $product): bool
                                    {
                                        return false;
                                    }
                                };
                            }

                            public function addCustomOption(string $code, string $value): void
                            {
                            }

                            public function setIsSuperMode(bool $isSuperMode): void
                            {
                            }
                        };
                    }
                },
                new class($rules) extends CollectionFactory {
                    public function __construct(private array $rules)
                    {
                    }

                    public function create(): object
                    {
                        return new class($this->rules) extends \ArrayIterator {
                            public function addFieldToFilter(string $field, mixed $condition): self
                            {
                                return $this;
                            }

                            public function setOrder(string $field, string $direction): self
                            {
                                return $this;
                            }
                        };
                    }
                },
                new class implements LoggerInterface {
                    public function warning(string|\Stringable $message, array $context = []): void
                    {
                    }

                    public function critical(string|\Stringable $message, array $context = []): void
                    {
                    }
                }
            );
        }

        private function quote(array $items, string $appliedRuleIds): Quote
        {
            return new Quote(
                new class {
                    public function getWebsiteId(): int
                    {
                        return 1;
                    }

                    public function getId(): int
                    {
                        return 1;
                    }
                },
                new class($appliedRuleIds) {
                    public function __construct(private string $appliedRuleIds)
                    {
                    }

                    public function getAppliedRuleIds(): string
                    {
                        return $this->appliedRuleIds;
                    }
                },
                $items
            );
        }

        private function paidItem(string $sku, float $qty, string $appliedRuleIds): QuoteItem
        {
            $item = new QuoteItem($sku, $qty, $appliedRuleIds);
            $item->setProduct(new class($sku) {
                public function __construct(private string $sku)
                {
                }

                public function getSku(): string
                {
                    return $this->sku;
                }

                public function setIsSuperMode(bool $isSuperMode): void
                {
                }
            });
            $item->setProductId(1);
            $item->setItemId(1);

            return $item;
        }

        private function rule(int $ruleId, array $data): object
        {
            return new class($ruleId, $data) {
                public function __construct(private int $ruleId, private array $data)
                {
                }

                public function getId(): int
                {
                    return $this->ruleId;
                }

                public function getData(string $key): mixed
                {
                    return $this->data[$key] ?? null;
                }
            };
        }

        private function findGiftItem(Quote $quote, string $sku): ?QuoteItem
        {
            foreach ($quote->getAllItems() as $item) {
                if ($item->getSku() === $sku && $item->getOptionByCode('niziou_freegift_item')) {
                    return $item;
                }
            }

            return null;
        }
    }
}
