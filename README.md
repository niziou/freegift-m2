# Niziou Free Gift

Magento 2 / Adobe Commerce module that automatically adds one free gift item to qualifying carts. It supports a website-scoped global gift SKU and rule-specific gift SKUs on cart price rules.

## Compatibility

- Adobe Commerce / Magento Open Source 2.4.8-p4 and the 2.4.8 release line
- PHP 8.3 or 8.4
- Composer 2

Adobe lists Adobe Commerce 2.4.8-p4 as the latest stable 2.4.x patch release as of April 24, 2026. Adobe Commerce 2.4.9 is currently listed as beta, so this module targets 2.4.8-p4.

## Features

- Website-scope global configuration under Stores > Configuration > Sales > Free Gift.
- Optional free gift fields on cart price rules.
- Rule-specific gift SKU takes priority over the global SKU.
- Optional Buy X Get Same Product Free settings on cart price rules.
- Adds only simple or virtual products without required options.
- Forces standard gift quantity to 1, disables discounts for gift lines, and sets gift prices to 0.00.
- Removes obsolete gift items when the cart no longer qualifies or a different rule-specific gift applies.

## Installation

Install with Composer from this repository or copy the module directory to `app/code/Niziou/FreeGift`.

```bash
bin/magento module:enable Niziou_FreeGift
bin/magento setup:upgrade
bin/magento cache:flush
```

## Configuration

1. Create an enabled, in-stock simple or virtual product for the gift.
2. Make sure the product has no required custom options and is assigned to the target website.
3. Go to Stores > Configuration > Sales > Free Gift.
4. Enable the global gift and enter the gift SKU.

For cart price rules, go to Marketing > Cart Price Rules and expand the Free Gift fieldset in the Actions area. Enable the rule gift and enter a gift SKU. When that rule applies to the quote, its SKU overrides the global gift SKU.

To configure Buy One Get One Free, create a cart price rule that targets the paid product through the normal Magento rule conditions/action conditions. In the Free Gift fieldset, enable Buy X Get Same Product Free and set Buy Quantity to `1` and Free Quantity to `1`. Leave BOGO Gift Product SKU empty to add a separate free line for the same SKU, or enter a different simple/virtual product SKU to use it as the gift. The module recalculates the gift quantity from the paid line quantity. Use Max Free Quantity to cap the number of free units.

## Testing

Run the focused unit suite from the module root:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```
