# Free Gift Magento 2 Module

This module adds a configurable free gift feature for Magento 2.4.x storefronts. Admins can define a global free gift SKU per website or attach a gift to specific cart price rules. Qualifying carts automatically receive the gift at $0.00 with a locked quantity of 1.

## Features
- Website-scope configuration toggle and SKU selection (Stores > Configuration > Sales > Free Gift).
- Additional fields on cart price rules to enable a rule-specific gift SKU.
- Automatic quote observer that adds the configured gift when eligible and removes it when no longer applicable.
- Safeguards for invalid product types, required options, or unavailable products.
- Basic PHPUnit coverage for the configuration provider.

## Installation
1. Copy the `app/code/Freegift/FreeGift` directory into your Magento installation.
2. Run `bin/magento module:enable Freegift_FreeGift`.
3. Execute `bin/magento setup:upgrade` to apply the schema changes for sales rules.
4. Clear caches: `bin/magento cache:flush`.

## Usage
- Set a global gift SKU under Stores > Configuration > Sales > Free Gift. Use a simple or virtual product without required options.
- On a cart price rule, expand the **Free Gift** fieldset to enable a rule-specific gift SKU. This overrides the global SKU when the rule applies (e.g., via coupon).
- The module keeps the gift price at zero, forces quantity to one, and removes the gift when the rule or configuration no longer qualifies.

## Testing
Run PHPUnit from your Magento root (ensure the Magento testing bootstrap is available):

```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Freegift/FreeGift/Test/Unit
```
