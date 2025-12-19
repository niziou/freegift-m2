<?php
namespace Freegift\FreeGift\Plugin\Adminhtml\SalesRule;

use Magento\SalesRule\Ui\DataProvider\Rule\Form\Modifier\Actions;

class FormModifier
{
    private const FIELDSET = 'freegift_settings';

    public function afterGetMeta(Actions $subject, array $meta): array
    {
        if (!isset($meta['actions'])) {
            return $meta;
        }

        $meta['actions']['children'][self::FIELDSET] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Free Gift'),
                        'componentType' => 'fieldset',
                        'collapsible' => true,
                        'sortOrder' => 90,
                        'dataScope' => 'data',
                    ],
                ],
            ],
            'children' => [
                'freegift_enabled' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('Enable Free Gift for this Rule'),
                                'componentType' => 'field',
                                'formElement' => 'checkbox',
                                'prefer' => 'toggle',
                                'dataScope' => 'freegift_enabled',
                                'dataType' => 'boolean',
                                'sortOrder' => 10,
                                'valueMap' => ['true' => 1, 'false' => 0],
                            ],
                        ],
                    ],
                ],
                'freegift_product_sku' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('Gift Product SKU (optional)'),
                                'componentType' => 'field',
                                'formElement' => 'input',
                                'dataType' => 'text',
                                'dataScope' => 'freegift_product_sku',
                                'sortOrder' => 20,
                                'notice' => __('Use a simple or virtual product without required options. Falls back to global setting when empty.'),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $meta;
    }

    public function afterGetData(Actions $subject, array $data): array
    {
        foreach ($data as &$ruleData) {
            if (!isset($ruleData['rule_id'])) {
                continue;
            }
            $rule = $ruleData;
            $ruleData['freegift_enabled'] = isset($rule['freegift_enabled'])
                ? (int)$rule['freegift_enabled']
                : 0;
            $ruleData['freegift_product_sku'] = $rule['freegift_product_sku'] ?? null;
        }

        return $data;
    }
}
