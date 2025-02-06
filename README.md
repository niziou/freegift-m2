# Nizou_FreeGift Magento 2 Module

This open-source Magento 2 module automatically adds a free gift to the cart when the cart’s base subtotal (excluding any free gift items) exceeds a configured threshold. If the subtotal falls below the threshold, the free gift is automatically removed.

## Features

- **Automatic Free Gift Addition:** When the base subtotal (excluding the free gift) reaches a configured threshold, the module automatically adds a free gift product to the cart.
- **Multiple Thresholds:** Configure multiple free gift rules. For example, you might offer one gift when the subtotal exceeds 100 units and a different gift when it exceeds 200 units.
- **Price and Discount Display:** The free gift is added with a custom price of 0.01. Using Magento’s built-in Cart Price Rules, you can apply a discount equivalent to the difference between the gift’s actual value and the 0.01 price so the customer sees the benefit.
- **Admin Configuration:** Manage the module settings under **Stores > Configuration > Nizou > Free Gift Configuration**.

## Requirements

- Magento 2.4.7
- PHP 8.3
- Familiarity with Magento 2 module best practices

## Installation

1. Place the module in `app/code/Nizou/FreeGift`.
2. Run the upgrade script:
   php bin/magento setup:upgrade
3. Clean the cache:
   php bin/magento cache:clean

## Configuration

Navigate to **Stores > Configuration > Nizou > Free Gift Configuration**. Set the module as enabled and enter your free gift rules in JSON format. For example:

```json
[
 {"threshold": 100, "gift_product_sku": "FREEGIFT001", "gift_actual_price": 15},
 {"threshold": 200, "gift_product_sku": "FREEGIFT002", "gift_actual_price": 30}
]
