<?php
declare(strict_types=1);

namespace Niziou\FreeGift\Plugin\Adminhtml\SalesRule;

use Magento\SalesRule\Ui\DataProvider\Rule\Form\Modifier\Actions;

class FormModifier
{
    private const FIELDSET = 'niziou_freegift_settings';

    public function afterModifyMeta(Actions $subject, array $meta): array
    {
        if (!isset($meta['actions']['children'])) {
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
                                'valueMap' => ['false' => 0, 'true' => 1],
                            ],
                        ],
                    ],
                ],
                'freegift_product_sku' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('Gift Product SKU'),
                                'componentType' => 'field',
                                'formElement' => 'input',
                                'dataType' => 'text',
                                'dataScope' => 'freegift_product_sku',
                                'sortOrder' => 20,
                                'notice' => __('Use a simple or virtual product without required options. Empty value falls back to the global configuration.'),
                            ],
                        ],
                    ],
                ],
                'freegift_bogo_enabled' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('Enable Buy X Get Same Product Free'),
                                'componentType' => 'field',
                                'formElement' => 'checkbox',
                                'prefer' => 'toggle',
                                'dataScope' => 'freegift_bogo_enabled',
                                'dataType' => 'boolean',
                                'sortOrder' => 30,
                                'valueMap' => ['false' => 0, 'true' => 1],
                            ],
                        ],
                    ],
                ],
                'freegift_bogo_buy_qty' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('BOGO Buy Quantity'),
                                'componentType' => 'field',
                                'formElement' => 'input',
                                'dataType' => 'number',
                                'dataScope' => 'freegift_bogo_buy_qty',
                                'sortOrder' => 40,
                                'notice' => __('Number of paid items required for each free same-SKU item.'),
                            ],
                        ],
                    ],
                ],
                'freegift_bogo_product_sku' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('BOGO Gift Product SKU'),
                                'componentType' => 'field',
                                'formElement' => 'input',
                                'dataType' => 'text',
                                'dataScope' => 'freegift_bogo_product_sku',
                                'sortOrder' => 45,
                                'notice' => __('Leave empty to gift the same SKU as the qualifying paid item.'),
                            ],
                        ],
                    ],
                ],
                'freegift_bogo_free_qty' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('BOGO Free Quantity'),
                                'componentType' => 'field',
                                'formElement' => 'input',
                                'dataType' => 'number',
                                'dataScope' => 'freegift_bogo_free_qty',
                                'sortOrder' => 50,
                            ],
                        ],
                    ],
                ],
                'freegift_bogo_max_free_qty' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('BOGO Max Free Quantity'),
                                'componentType' => 'field',
                                'formElement' => 'input',
                                'dataType' => 'number',
                                'dataScope' => 'freegift_bogo_max_free_qty',
                                'sortOrder' => 60,
                                'notice' => __('Leave empty for no limit.'),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $meta;
    }

    public function afterModifyData(Actions $subject, array $data): array
    {
        foreach ($data as &$ruleData) {
            if (!is_array($ruleData)) {
                continue;
            }

            $ruleData['freegift_enabled'] = isset($ruleData['freegift_enabled']) ? (int)$ruleData['freegift_enabled'] : 0;
            $ruleData['freegift_product_sku'] = $ruleData['freegift_product_sku'] ?? '';
            $ruleData['freegift_bogo_enabled'] = isset($ruleData['freegift_bogo_enabled'])
                ? (int)$ruleData['freegift_bogo_enabled']
                : 0;
            $ruleData['freegift_bogo_buy_qty'] = isset($ruleData['freegift_bogo_buy_qty'])
                ? (int)$ruleData['freegift_bogo_buy_qty']
                : 1;
            $ruleData['freegift_bogo_product_sku'] = $ruleData['freegift_bogo_product_sku'] ?? '';
            $ruleData['freegift_bogo_free_qty'] = isset($ruleData['freegift_bogo_free_qty'])
                ? (int)$ruleData['freegift_bogo_free_qty']
                : 1;
            $ruleData['freegift_bogo_max_free_qty'] = $ruleData['freegift_bogo_max_free_qty'] ?? null;
        }

        return $data;
    }
}
