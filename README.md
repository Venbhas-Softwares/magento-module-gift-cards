# Venbhas Gift Card (`Venbhas_GiftCard`)

Gift card products, checkout redemption, balance handling, emails, usage ledger, and customer account UI for Magento 2 Open Source.

## Features

- **Configurable gift card products** with amount rules, optional custom amount, storefront gift options (recipient, message, delivery type).
- **Delivery modes (store configuration + product):** virtual (email), physical (ship card), or customer choice when `both` is configured.
- **Checkout redemption:** quote total collector applies gift cards to the grand total (including zero total).
- **Ledger** in `venbhas_giftcard_transaction`:
  - **`checkout_apply`** — logged when an order is placed with gift card(s) applied (intent / audit).
  - **`redeem`** — logged when balances are deducted on **invoice payment** (financial movement).
- **Balance deduction:** occurs on **`sales_order_invoice_pay`**, not at order placement. Flag `sales_order.venbhas_giftcard_redeemed` prevents double processing.
- **Apply lock:** first successful apply locks the card to redeemer customer id or guest email (`GiftCardRedeemValidator`).
- **Admin:** gift card codes grid, gift card transactions grid, customer tab “Gift Card Transactions”, order usage display.
- **Customer account:** “My Gift Cards” (codes **this customer applied**, i.e. redeemer only), “My Gift Card Transactions”.
- **Emails:**
  - **Purchased gift card:** sent to recipient on invoice payment for **virtual** delivery only; **physical** delivery skips automated code email (`GiftCardSender`).
  - **Usage receipt:** optional email on invoice payment summarizing redemption when gift cards were applied to the order (`GiftCardRedemptionReceiptSender`).
- **Checkout “My codes”** (logged-in customers): reuse locked cards with remaining balance (`Controller/Checkout/MyCodes.php` + payment UI).

## Accounting flow (recommended mental model)

| Step | What happens |
|------|----------------|
| Order placed | Quote→order persistence; **`checkout_apply`** ledger rows; **no** DB balance deduction on codes yet. |
| Invoice paid | **`redeem`** rows; **`venbhas_giftcard_code.balance_amount`** updated; `venbhas_giftcard_redeemed` set on order. |

## Data model

### Tables

**`venbhas_giftcard_code`**

- Codes, **`balance_amount`** (current balance), **`initial_value`**, currency, status (`pending` / `active` / `inactive`).
- Locks: **`redeemer_customer_id`**, **`redeemer_email`** (first checkout apply).
- Gift product context: **`customer_id`** (buyer), **`recipient_email`**, **`delivery_type`** (`virtual` \| `physical`), shipping address columns when physical.

**`venbhas_giftcard_transaction`**

- **`action`:** `checkout_apply` \| `redeem`
- **`order_id`**, **`invoice_id`**, amounts, **`balance_after`**, customer snapshot.

### Sales / quote columns

Persisted gift card fields on **`quote`** and **`sales_order`:**

- `venbhas_giftcard_amount`, `base_venbhas_giftcard_amount`
- `venbhas_giftcard_codes` (CSV)
- `venbhas_giftcard_applied` (JSON array: `code`, `base_amount`)
- `venbhas_giftcard_usage_details` (mirror for display)
- `venbhas_giftcard_redeemed` (0/1)

Checkout totals extension attributes (API / Hyvä consumption): see `Plugin/Quote/CartTotalRepositoryPlugin.php`.

## Important classes

| Area | Class / path |
|------|----------------|
| Quote total | `Model/Total/Quote/GiftCard.php` |
| Redeem + balance | `Model/GiftCardRedeemer.php` (invoice only) |
| Checkout ledger | `Model/GiftCardCheckoutTransactionLogger.php` |
| Order entity id helper | `Model/SalesOrderEntityIdResolver.php` |
| Quote→order options (admin line items) | `Plugin/Quote/Item/ToOrderItemPlugin.php` |
| Customer transaction query | `Model/CustomerGiftCardTransactionsLoader.php` |
| Issue codes on invoice | `Model/GiftCardIssuer.php` |
| Email | `Model/Email/GiftCardSender.php`, `Model/Email/GiftCardRedemptionReceiptSender.php` |

## Observers (selected)

- `ConvertQuoteToOrder` — field sync / usage details.
- `LogGiftCardCheckoutUsageOnOrderPlace` — `checkout_apply` ledger.
- `GenerateGiftCardOnInvoicePay` — redeem balances, issue purchased codes, send emails.
- `PersistGiftCardFieldsOnQuoteItem` — gift options + **`additional_options`** (shows in cart, emails, admin order items when copied to order item).

## Installation / deploy

```bash
bin/magento setup:upgrade
bin/magento cache:flush
```

After DI changes (plugins): `bin/magento setup:di:compile` in production mode.

## Verification checklist

- **Checkout:** apply code, place order; `sales_order.venbhas_giftcard_applied` populated; ledger has **`checkout_apply`** with order id.
- **Invoice:** capture/pay invoice; code **`balance_amount`** decreases; ledger has **`redeem`** with `invoice_id`; order flag redeemed.
- **Virtual purchase:** recipient receives gift card email on invoice; **physical** purchase does not email the code.
- **Admin order:** line item options show gift fields including **Gift card delivery** (after quote→order conversion plugin).
- **Customer account:** “My Gift Cards” lists only cards where this account is **redeemer**; “My Gift Card Transactions” lists ledger rows for that customer (including order-linked rows).

## Notes

- Schema uses **`balance_amount`** for current balance (legacy column names may exist on older DBs; several readers fall back).
- For local email testing, use an SMTP catcher (e.g. Mailpit) and configure Magento SMTP to `127.0.0.1:1025`.
