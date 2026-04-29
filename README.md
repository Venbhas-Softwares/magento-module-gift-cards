# Venbhas Gift Card (`Venbhas_GiftCard`)

Magento 2 gift card module with:

- gift card product purchase flow
- wallet-based gift amount usage in checkout
- transaction ledger for wallet credits/debits
- customer account transaction history
- admin grids and admin customer tab
- invoice / credit memo totals integration

## Current behavior

This module no longer uses the old per-code checkout redemption flow.

Current model:

- Gift card **products** create rows in `venbhas_giftcard_code`
- Customers **add a code to their wallet** from My Account or checkout popup
- Checkout applies a **wallet amount**, not individual gift card codes
- Wallet movements are tracked in `venbhas_giftcard_transaction`

## Main features

- **Gift card product type** with configurable amount and recipient/delivery fields
- **Gift card code issuance on invoice payment only**
  - no placeholder or `pending-*` rows on order placement
- **Wallet flow**
  - adding a valid gift code creates a `credit` transaction
  - applying wallet amount in checkout creates a `debit` transaction on order placement
- **Cancel / refund credit-back**
  - canceling an order credits the wallet back with description `order canceled`
  - refunding an order credits the wallet back with description `order refunded (...)`
- **Refund protection for gift card products**
  - if any issued code from the order is already redeemed, refund is blocked
- **Canceled gift cards are invalid**
  - refunded gift-card-product codes are marked `is_cancelled = 1`
  - canceled codes cannot be added again from checkout or My Account
- **Admin totals integration**
  - Gift Card row is shown on invoice create / view
  - Gift Card row is shown on credit memo create / view

## Accounting model

| Step | What happens |
|------|--------------|
| Gift card product ordered | No row is inserted into `venbhas_giftcard_code` yet |
| Invoice paid for gift card product | Actual gift card code row is created in `venbhas_giftcard_code` |
| Customer adds code to wallet | `credit` row inserted into `venbhas_giftcard_transaction` |
| Customer applies wallet amount in checkout | Quote/order stores `venbhas_giftcard_amount`; order placement logs `debit` row |
| Order canceled | Wallet is credited back with `credit` row and description `order canceled` |
| Credit memo refund | Wallet is credited back with `credit` row and description `order refunded (...)` |
| Gift card product refunded | Issued code rows for that order are marked `is_cancelled = 1` |

## Tables

### `venbhas_giftcard_code`

Main fields used by current flow:

- `code`
- `amount`
- `purchased_by`
- `purchased_by_email`
- `redeemed_by`
- `redeemed_email`
- `product_id`
- `order_id`
- recipient / sender / delivery fields
- `is_reedemed`
- `is_cancelled`

Notes:

- `amount` is the issued value for the gift card code
- `is_reedemed` means the code was already added to a wallet
- `is_cancelled` means the code became invalid because the related gift card product order was refunded

### `venbhas_giftcard_transaction`

Main fields used by current flow:

- `giftcard_id`
- `transaction_type`
- `description`
- `amount`
- `previous_balance`
- `current_balance`
- `order_id`
- `customer_id`
- `customer_email`
- `store_id`

Transaction types currently used:

- `credit`
- `debit`
- legacy `checkout_apply`
- legacy `redeem`

Current wallet math:

- `credit` adds to wallet
- `debit`, `checkout_apply`, and `redeem` subtract from wallet

## Sales entity fields

### `quote`

- `venbhas_giftcard_amount`
- `base_venbhas_giftcard_amount`

### `sales_order`

- `venbhas_giftcard_amount`
- `base_venbhas_giftcard_amount`

### `sales_invoice`

- `venbhas_giftcard_amount`
- `base_venbhas_giftcard_amount`

These invoice fields are used so invoice create/view can show the Gift Card row and support partial invoicing.

## Important classes

| Area | Class |
|------|-------|
| Gift card issuance | `Model/GiftCardIssuer.php` |
| Checkout wallet debit logging | `Model/GiftCardCheckoutTransactionLogger.php` |
| Wallet balance loader | `Model/CustomerGiftCardTransactionsLoader.php` |
| Wallet ledger writer | `Model/WalletLedger.php` |
| Quote total collector | `Model/Total/Quote/GiftCard.php` |
| Invoice total collector | `Model/Total/Invoice/GiftCard.php` |
| Credit memo total collector | `Model/Total/Creditmemo/GiftCard.php` |
| Add code to wallet | `Controller/Account/AddGiftcard.php` |
| Checkout apply wallet amount | `Controller/Checkout/Apply.php` |
| Checkout remove wallet amount | `Controller/Checkout/Remove.php` |
| Checkout wallet balance API | `Controller/Checkout/Wallet.php` |

## Events / observers

- `sales_model_service_quote_submit_before`
  - `Observer/ConvertQuoteToOrder.php`
  - copies gift amount fields from quote to order

- `sales_model_service_quote_submit_success`
  - `Observer/LogGiftCardCheckoutUsageOnOrderPlace.php`
  - logs checkout wallet usage to transaction table

- `sales_order_place_after`
  - `Observer/LogGiftCardCheckoutUsageOnOrderPlace.php`
  - fallback for usage logging

- `sales_order_invoice_pay`
  - `Observer/GenerateGiftCardOnInvoicePay.php`
  - issues purchased gift card codes and sends emails

- `order_cancel_after`
  - `Observer/CreditWalletOnOrderCancel.php`
  - credits wallet back when applied gift amount order is canceled

- `sales_order_creditmemo_save_before`
  - `Observer/PreventGiftCardRefundIfRedeemed.php`
  - blocks refund of gift card products when issued codes were already redeemed

- `sales_order_creditmemo_refund`
  - `Observer/CreditWalletOnCreditmemoRefund.php`
  - credits wallet back for refund
  - marks gift card product codes as canceled

## Frontend behavior

### Product page

- gift card fields render on gift card product page
- amount, recipient, message, and delivery data are stored on quote item options
- invoicing later issues the real code

### My Account

- customers can add a gift card code to wallet
- canceled codes are rejected with `Card is not valid.`
- transaction page shows:
  - wallet balance
  - signed amount (`+` credit / `-` debit)
  - balance after transaction
  - order links where available

### Checkout

- customer sees wallet balance
- can apply amount from wallet
- can remove applied amount
- can add a new gift card from popup
- add popup uses same backend validation as My Account
- canceled codes are rejected with `Card is not valid.`

## Admin behavior

- Gift Card Codes grid
- Gift Card Transactions grid
- clickable order and customer links
- amount shown with currency formatting
- customer edit tab shows transaction history
- invoice create / view shows Gift Card row
- credit memo create / view shows Gift Card row

## Commands

### Recalculate wallet balances

```bash
bin/magento venbhas:giftcard:recalc-wallet
```

Rebuilds `previous_balance` and `current_balance` for existing transaction rows.

## Deploy / upgrade

```bash
bin/magento setup:upgrade
bin/magento cache:clean
```

If running production mode and DI changed:

```bash
bin/magento setup:di:compile
```

## Quick verification checklist

- place order using wallet amount
- invoice order and verify invoice total excludes gift amount
- cancel pending order and verify wallet gets `order canceled` credit row
- refund invoiced order and verify wallet gets `order refunded (...)` credit row
- refund gift card product order and verify code row gets `is_cancelled = 1`
- try adding canceled code in checkout / My Account and verify `Card is not valid.`
