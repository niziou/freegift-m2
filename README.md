# Niziou_FreeGift Magento 2 Module

This open-source Magento 2 module automatically adds a free gift to the cart when the cart’s base subtotal (excluding any free gift items) exceeds a configured threshold. If the subtotal falls below the threshold, the free gift is automatically removed.

## Features

- **Automatic Free Gift Addition:** When the base subtotal (excluding the free gift) reaches a configured threshold, the module automatically adds a free gift product to the cart.
- **Multiple Thresholds:** Configure multiple free gift rules. For example, you might offer one gift when the subtotal exceeds 100 units and a different gift when it exceeds 200 units.
- **Price and Discount Display:** The free gift is added with a custom price of 0.01. Using Magento’s built-in Cart Price Rules, you can apply a discount equivalent to the difference between the gift’s actual value and the 0.01 price so the customer sees the benefit.
- **Admin Configuration:** Manage the module settings under **Stores > Configuration > Niziou > Free Gift Configuration**.

## Requirements

- Magento 2.4.7
- PHP 8.3
- Familiarity with Magento 2 module best practices

## Installation

1. Place the module in `app/code/Niziou/FreeGift`.
2. Run the upgrade script:
   php bin/magento setup:upgrade
3. Clean the cache:
   php bin/magento cache:clean

## Configuration
# Configuration & Testing (with Cart Price Rule)

## 1. Add the Free Gift Product to Magento Catalog

1. Log in to the Magento Admin.
2. Navigate to: Catalog > Products
3. Create a New Product:
    - **Product Name:** e.g., Free Gift
    - **SKU:** e.g., gift_sku_1  
      (Make sure this SKU matches the one used in your module configuration.)
    - **Price:** Set the full (actual) price of the gift (e.g., 15.00).  
      (This value is used later for display purposes in the discount.)
    - **Tax Class:** Choose None (or as appropriate).
    - **Visibility:** Set to Not Visible Individually.
    - **Stock Status:** Ensure it is In Stock.
    - **Quantity:** Set to at least 1.
    - **Weight:** Set to 0 (if shipping weight is a factor).
4. Save the Product.

---

## 2. Configure the Free Gift Module

1. Navigate to: Stores > Configuration > Niziou > Free Gift Configuration
2. Enable the Module:
    - Set **Enabled** to Yes.
3. Enter the Free Gift Rules:  
   In the field labeled **Free Gift Rules (JSON Format)**, enter your rules. For example:

   JSON:
   [
   {"threshold": 100, "gift_product_sku": "gift_sku_1", "gift_actual_price": 15},
   {"threshold": 200, "gift_product_sku": "gift_sku_2", "gift_actual_price": 30}
   ]

   (This configuration means that when the cart subtotal (excluding the free gift) reaches 100, the product with SKU "gift_sku_1" is added. If the subtotal is 200 or higher, then, if configured, the product with SKU "gift_sku_2" is used instead.)
4. Save Configuration and Flush the Cache:  
   Run in the terminal:
   php bin/magento cache:flush

---

## 3. Create a Cart Price Rule for Discount Display

The Cart Price Rule is used to display a discount in the cart/checkout so that customers see they are “receiving” the gift’s full value even though it is added at a custom price of 0.01.

1. Navigate to: Marketing > Cart Price Rules
2. Click “Add New Rule” and configure as follows:

   **Rule Information:**
    - **Rule Name:** Free Gift Discount
    - **Description:** (Optional) A rule to apply a discount corresponding to the free gift’s value.
    - **Active:** Yes
    - **Websites:** Select the website(s) where the promotion should apply.
    - **Customer Groups:** Select the groups (e.g., General).
    - **Coupon:** Choose No Coupon for an automatic application.
    - **Uses per Customer:** Leave blank or as needed.

   **Conditions:**
    - Click on the green "+" icon to add a condition.
    - Set the condition:  
      *If ALL of these conditions are TRUE:*
        - Subtotal (or Base Subtotal) is greater than or equal to 100.00  
          (Adjust the threshold value to match your free gift rule.)

   **Actions:**
    - **Apply:** Choose "Buy X get Y free (discount amount is Y)".  
      (Note: In some Magento setups, you may instead use a fixed discount action. The intent is to show a discount equal to the difference between the gift’s actual price and its custom price of 0.01.)
    - **Discount Amount:** Enter the value equal to the difference between the free gift’s full price and 0.01.  
      For example, if the free gift’s full price is 15.00, enter 14.99.
    - **Maximum Qty Discount is Applied To:** Enter 1 (since the free gift is only one item).
    - **Discount Qty Step (Buy X):** Enter 1.
    - **Apply to Shipping Amount:** Set to No.
    - **Stop Further Rules Processing:** Set to Yes.
    - **(Optional) Free Gift SKU Field:**  
      If your Magento installation (or any third-party extension) provides an option to specify a “Free Gift SKU” on the rule, enter gift_sku_1 here so that the discount applies only to the free gift.

3. Save the Rule.

---

## 4. Test the Full Feature on the Frontend

1. **Clear Cache and Reindex (if necessary):**  
   Run in the terminal:
   php bin/magento cache:flush  
   php bin/magento indexer:reindex

2. **On the Frontend:**
    - **Add Regular Products:**  
      Add one or more regular products to your cart until the cart’s subtotal (excluding any free gift) reaches or exceeds the configured threshold (e.g., 100).
    - **Observe the Free Gift:**  
      The module’s observer should automatically add the free gift product (with SKU gift_sku_1) to the cart at a custom price of 0.01.
    - **Check the Discount:**  
      The Cart Price Rule should trigger and display a discount in the cart totals equivalent to the difference (e.g., 14.99). This visually confirms that the customer is receiving the full value of the free gift.
    - **Test Removal:**  
      Remove items so that the subtotal falls below the threshold and verify that both the free gift and discount are removed.
    - **Test Multiple Thresholds (if configured):**  
      If you have multiple rules (e.g., one for 100 and another for 200), ensure that the correct free gift is added based on the current cart subtotal.

---

## Summary

By following these steps:
- The free gift product is correctly set up in your catalog.
- The module is enabled and configured with JSON rules.
- A Cart Price Rule is in place to display the discount, reflecting the free gift’s full value.
- The entire process is tested on the frontend, confirming that when the cart subtotal meets the defined threshold, the free gift is added (and the discount appears) and is removed if the subtotal drops below the threshold.
