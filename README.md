# Venbhas Gift Card (`Venbhas_GiftCard`)

This module adds gift-card products and checkout redemption support to Magento 2, and records gift-card usage against orders.

## Features

- **Gift card code issuance** on invoice payment (pending rows are reserved at order placement).
- **Apply gift card(s) at checkout** to reduce the order grand total (including full coverage to \(0\)).
- **Usage ledger** in `venbhas_giftcard_transaction`.
- **Admin + storefront order view display** of:
  - Total gift-card amount applied to the order
  - Which gift card code(s) were used and the amount used per code
- **Customer account pages** for gift card transactions and active cards.

## Data model (tables / columns)

### Tables

- **`venbhas_giftcard_code`**
  - Stores gift card codes, balances, status, and (optionally) reservation details for gift card products.
- **`venbhas_giftcard_transaction`**
  - Stores a ledger of activity against a gift card.
  - Used actions:
    - `checkout_apply`: logged when an order is placed with gift card(s) applied
    - `redeem`: logged when the gift card balance is actually deducted (invoice payment)

### Sales order columns

Gift-card order data is persisted onto `sales_order`:

- **`venbhas_giftcard_amount` / `base_venbhas_giftcard_amount`**
  - Total amount applied from gift cards for the order.
- **`venbhas_giftcard_codes`**
  - CSV list of applied gift card codes (uppercased).
- **`venbhas_giftcard_applied`**
  - JSON breakdown of applied codes + used amount per code.
  - Example:

```json
[
  {"code":"ABCD1234","base_amount":34.00},
  {"code":"XYZ999","base_amount":5.00}
]
```

- **`venbhas_giftcard_usage_details`**
  - JSON mirror used by order view templates (kept in sync from `venbhas_giftcard_applied` during quote→order conversion).
- **`venbhas_giftcard_redeemed`**
  - Flag to prevent double-deduction on invoice payment.

### Quote columns

Gift-card checkout totals are calculated on the quote and persisted to `quote`:

- `venbhas_giftcard_amount`, `base_venbhas_giftcard_amount`
- `venbhas_giftcard_codes`
- `venbhas_giftcard_applied`

Checkout UI also uses a computed JSON payload (balances and preview amounts) exposed via totals extension attributes:

- `TotalsInterface` extension attributes:
  - `venbhas_giftcard_codes`
  - `venbhas_giftcard_balance_details`

## How it works (high level)

### Applying gift cards at checkout

- Gift card codes are stored on the quote.
- The quote total collector calculates how much can be applied and stores:
  - `venbhas_giftcard_amount` (total)
  - `venbhas_giftcard_applied` (JSON breakdown per code)

### Quote → order persistence

On order placement, gift-card fields are copied from quote to order:

- Fieldset mapping: `etc/fieldset.xml` (`sales_convert_quote` → `to_order`)
- Observer: `Venbhas\GiftCard\Observer\ConvertQuoteToOrder`
  - Ensures the order gets the gift-card fields
  - Mirrors `venbhas_giftcard_applied` into `venbhas_giftcard_usage_details` for order views

### Transaction logging

When the order is placed, this module records `checkout_apply` rows in `venbhas_giftcard_transaction`:

- Observer: `Venbhas\GiftCard\Observer\LogGiftCardCheckoutUsageOnOrderPlace`
- Logger: `Venbhas\GiftCard\Model\GiftCardCheckoutTransactionLogger`
  - Reads `sales_order.venbhas_giftcard_applied`
  - Inserts per-code rows with `action = checkout_apply`

### Balance deduction

The actual gift card balance deduction is performed on invoice payment:

- Observer: `Venbhas\GiftCard\Observer\GenerateGiftCardOnInvoicePay`
- Redeemer: `Venbhas\GiftCard\Model\GiftCardRedeemer`
  - Deducts balance and logs `redeem` transactions

## Display in admin + storefront

- **Totals line item**:
  - Block: `Venbhas\GiftCard\Block\Order\Totals\GiftCard`
  - Layout:
    - `view/adminhtml/layout/sales_order_view.xml`
    - `view/frontend/layout/sales_order_view.xml`
- **Per-code usage table**:
  - Block: `Venbhas\GiftCard\Block\Order\GiftCardUsage`
  - Template:
    - `view/adminhtml/templates/order/giftcard_usage.phtml`
    - `view/frontend/templates/order/giftcard_usage.phtml`

## Installation / upgrade

After installing or changing the module schema:

```bash
bin/magento setup:upgrade
bin/magento cache:flush
```

## Verification checklist

- **Checkout**
  - Apply a gift card and place an order (including full coverage to \(0\)).
- **Database**
  - `sales_order` has non-empty `venbhas_giftcard_applied` JSON.
  - `venbhas_giftcard_transaction` has `checkout_apply` row(s) for the order.
- **Admin**
  - Sales order view shows the gift card total line and usage table (code + amount).
- **Storefront**
  - Customer order view shows the same usage details.

