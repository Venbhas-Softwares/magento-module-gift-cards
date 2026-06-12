# Venbhas_GiftCard

Gift Card product type module for Magento 2.

## Features

- Custom `giftcard` product type extending Simple product
- Configurable gift card amount (presets and/or custom amount)
- Delivery type support: Virtual (email), Physical (shipping), or Both
- Recipient and sender fields (name, email, message)
- Physical delivery address collection when applicable
- Gift card code generation on invoice
- Customer wallet: redeem codes to account balance, apply wallet amount at checkout
- Balance management and transaction history
- Admin order creation support with full configuration popup
- Luma/Blank storefront included; optional `Venbhas_GiftCardHyva` package for Hyvä account pages

## Admin Order Creation

The module fully supports creating gift card orders from the admin panel:

- **Product visibility**: Gift card products appear in the admin order search grid
- **Configuration popup**: Custom fields (amount, delivery type, recipient info, message) are displayed in the product configure modal
- **Price update**: Selected amount is applied as custom price on the quote item
- **Pre-population**: When re-opening the configure popup for an already-added item, all previously selected values are restored
- **Validation**: Product cannot be added without configuring the required amount field

## Configuration

Store-level settings are available under:
**Stores > Configuration > Venbhas > Gift Card**

Key options:
- Enable/disable module
- Minimum and maximum gift card amount
- Allow custom amount
- Delivery type (virtual / physical / both)
- Allow custom message
- Email template for purchase notifications

## Product Setup

1. Create a new product with type **Gift Card**
2. Configure amount presets (comma-separated) or enable custom amount
3. Set delivery type at product level or use store config default
4. Save and assign to categories

## Technical Architecture

### Product Type
- `Venbhas\GiftCard\Model\Product\Type\GiftCard` — Extends `Magento\Catalog\Model\Product\Type\Simple`
- `canConfigure()` returns `true` to enable the admin configure popup
- `_prepareProduct()` validates that amount is provided before adding to cart/quote
- `processBuyRequest()` returns gift card data for preconfigured values (enables field pre-population)

### Observers

| Event | Scope | Observer | Purpose |
|-------|-------|----------|---------|
| `checkout_cart_product_add_before` | Global | `ValidateGiftCardFieldsOnAddToCart` | Validates required fields on frontend |
| `checkout_cart_product_add_after` | Global | `PersistGiftCardFieldsOnQuoteItem` | Saves fields to quote item additional_options |
| `checkout_cart_product_add_after` | Global | `ApplyGiftCardAmountToQuoteItem` | Sets custom price from selected amount |
| `sales_model_service_quote_submit_before` | Global | `ConvertQuoteToOrder` | Copies wallet discount totals to order |
| `sales_model_service_quote_submit_success` | Global | `LogGiftCardCheckoutUsageOnOrderPlace` | Logs wallet usage on order place |
| `sales_order_invoice_pay` | Global | `GenerateGiftCardOnInvoicePay` | Issues gift card codes on invoice |
| `order_cancel_after` | Global | `CreditWalletOnOrderCancel` | Credits wallet on order cancel |
| `sales_order_creditmemo_save_before` | Global | `PreventGiftCardRefundIfRedeemed` | Blocks refund when code is redeemed |
| `sales_order_creditmemo_refund` | Global | `CreditWalletOnCreditmemoRefund` | Credits wallet on refund |
| `sales_quote_product_add_after` | Adminhtml | `PersistGiftCardFieldsOnAdminQuoteItem` | Handles persistence and pricing in admin |
| `catalog_product_save_before` | Adminhtml | `SaveGiftCardOptionsOnProduct` | Processes product-level gift card config |

### Layout Handles (Adminhtml)

- `catalog_product_view_type_giftcard` — Adds gift card fields to the admin composite configure popup
- `sales_order_create_index` — Fixes giftmessage.js initialization error on order create page

### Key Files

```
app/code/Venbhas/GiftCard/
├── Block/Product/View/Fields.php                         # Gift card fields block
├── Model/Product/Type/GiftCard.php                       # Product type model
├── Model/Product/GiftOptionsResolver.php                 # Resolves config/product options
├── Model/Config.php                                      # Module configuration
├── Observer/Adminhtml/PersistGiftCardFieldsOnAdminQuoteItem.php
├── etc/
│   ├── product_types.xml                                 # Registers giftcard product type
│   ├── sales.xml                                         # Registers available_product_type for admin grid
│   ├── events.xml                                        # Frontend observers
│   └── adminhtml/events.xml                              # Admin observers
└── view/adminhtml/
    ├── layout/catalog_product_view_type_giftcard.xml     # Configure popup layout
    └── templates/catalog/product/composite/fieldset/giftcard.phtml
```


## Installation

**Luma / Blank theme:**
```bash
bin/magento module:enable Venbhas_GiftCard
bin/magento setup:upgrade
bin/magento cache:clean
```

**Hyvä theme** (requires the base module plus the Hyvä integration package):
```bash
bin/magento module:enable Venbhas_GiftCard Venbhas_GiftCardHyva
bin/magento setup:upgrade
bin/magento cache:clean
```

## Module packages

| Package | Module | Purpose |
|---------|--------|---------|
| `venbhas/module-gift-card` | `Venbhas_GiftCard` | Core logic, admin, Luma PDP/checkout, Luma account pages |
| `venbhas/module-gift-card-hyva` | `Venbhas_GiftCardHyva` | Hyvä account transaction templates and pager |

## Requirements

- Magento 2.4.x
- PHP 8.1+
