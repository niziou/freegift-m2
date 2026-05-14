<?php
declare(strict_types=1);

namespace Magento\SalesRule\Ui\DataProvider\Rule\Form\Modifier {
    class Actions
    {
    }
}

namespace Niziou\FreeGift\Test\Unit\Plugin\Adminhtml\SalesRule {
    use Magento\SalesRule\Ui\DataProvider\Rule\Form\Modifier\Actions;
    use Niziou\FreeGift\Plugin\Adminhtml\SalesRule\FormModifier;
    use PHPUnit\Framework\TestCase;

    class FormModifierTest extends TestCase
    {
        public function testBogoFieldsAreAddedToFreeGiftFieldset(): void
        {
            $modifier = new FormModifier();
            $meta = $modifier->afterModifyMeta(new Actions(), ['actions' => ['children' => []]]);

            $children = $meta['actions']['children']['niziou_freegift_settings']['children'];

            self::assertArrayHasKey('freegift_bogo_enabled', $children);
            self::assertArrayHasKey('freegift_bogo_buy_qty', $children);
            self::assertArrayHasKey('freegift_bogo_product_sku', $children);
            self::assertArrayHasKey('freegift_bogo_free_qty', $children);
            self::assertArrayHasKey('freegift_bogo_max_free_qty', $children);
            self::assertSame(
                'freegift_bogo_product_sku',
                $children['freegift_bogo_product_sku']['arguments']['data']['config']['dataScope']
            );
        }

        public function testBogoDataDefaultsAreApplied(): void
        {
            $modifier = new FormModifier();
            $data = $modifier->afterModifyData(new Actions(), [
                10 => [
                    'freegift_enabled' => 1,
                    'freegift_product_sku' => 'GIFT',
                ],
            ]);

            self::assertSame(0, $data[10]['freegift_bogo_enabled']);
            self::assertSame(1, $data[10]['freegift_bogo_buy_qty']);
            self::assertSame('', $data[10]['freegift_bogo_product_sku']);
            self::assertSame(1, $data[10]['freegift_bogo_free_qty']);
            self::assertNull($data[10]['freegift_bogo_max_free_qty']);
        }
    }
}
