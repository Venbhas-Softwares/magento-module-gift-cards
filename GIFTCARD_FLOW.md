# Venbhas Gift Card



* ## Product type: 

## **Type code**: `giftcard`

* Registered in: `etc/product\_types.xml`

  * `modelInstance="Venbhas\\GiftCard\\Model\\Product\\Type\\GiftCard"`

Key class:

* `Model/Product/Type/GiftCard.php`

  * Extends Simple (`Magento\\Catalog\\Model\\Product\\Type\\Simple`)
  * Overrides:

    * `isVirtual($product)`: if delivery is **physical-only**, returns `false` so the line item becomes **shippable**
    * `canConfigure($product)`: always `true` (gift card products always have fields)



## Gift card product fields (admin): 

## Gift-card-specific **product attributes** are created via a data patch:

* `Setup/Patch/Data/AddGiftOptionsProductAttributes.php`

It creates an attribute group **“Gift Options”** and attributes that apply only to product type `giftcard`:

* **Delivery Type** (`GiftOptionsResolver::ATTR\_DELIVERY\_TYPE`)

  * Source model: `Model/Config/Source/Product/GiftDeliveryTypeWithConfig`
  * “Use configuration defaults” is supported (store config fallback)
* **Custom Amount Allowed** (`GiftOptionsResolver::ATTR\_ALLOW\_CUSTOM\_AMOUNT`)

  * Source model: `Model/Config/Source/Product/NullableYesNo`
* **Amounts Available** (`GiftOptionsResolver::ATTR\_AMOUNTS\_AVAILABLE`)

  * Textarea of comma-separated presets
* **Custom Message Allowed** (`GiftOptionsResolver::ATTR\_ALLOW\_CUSTOM\_MESSAGE`)

  * Source model: `Model/Config/Source/Product/NullableYesNo`

Store defaults (fallbacks) are defined in:

* `etc/config.xml`
* Admin configuration UI is in:

  * `etc/adminhtml/system.xml` (`Stores → Configuration → Venbhas → Gift Card`)



## Product page (frontend): 

## Layout injection for gift card PDP:

* `view/frontend/layout/catalog\_product\_view\_type\_giftcard.xml`

  * Adds block `Venbhas\\GiftCard\\Block\\Product\\View\\Fields`
  * Template: `view/frontend/templates/product/view/fields.phtml`

Block logic:

* `Block/Product/View/Fields.php`

  * `shouldRender()` checks module enabled + product type `giftcard`
  * Reads presets/min/max/delivery/message rules via:

    * `Model/Product/GiftOptionsResolver.php` (product attributes or fallback to config)
    * `Model/Config.php` (store config)

Posted field names (request payload):

* `venbhas\_giftcard\[amount]`
* `venbhas\_giftcard\[recipient\_name]`, `venbhas\_giftcard\[recipient\_email]`
* `venbhas\_giftcard\[sender\_name]`, `venbhas\_giftcard\[sender\_email]`
* `venbhas\_giftcard\[message]`
* `venbhas\_giftcard\[delivery\_type]`
* physical delivery fields when selected:

  * `delivery\_street`, `delivery\_city`, `delivery\_region`, `delivery\_postcode`, `delivery\_country`



## Add-to-cart: validation + persistence (quote item)

Events wired in `etc/events.xml`:

### 1\) Validation (before add)

* Event: `checkout\_cart\_product\_add\_before`
* Observer: `Observer/ValidateGiftCardFieldsOnAddToCart.php`

Validates:

* required text fields: recipient/sender
* message required if allowed by resolver/config
* amount:

  * must be > 0
  * must be within min/max
  * if custom amount not allowed → must match one of the presets
* delivery:

  * if product/store delivery type is `both` → posted `delivery\_type` must be `virtual` or `physical`
  * if physical delivery selected → address fields must be present

### 2\) Persist gift fields on quote item (after add)

* Event: `checkout\_cart\_product\_add\_after`
* Observer: `Observer/PersistGiftCardFieldsOnQuoteItem.php`

How it is stored:

* Adds/updates quote item option `additional\_options` (JSON)
* Each row includes:

  * `label`, `value`, `option\_code`
* This is the standard Magento mechanism that makes gift fields show on:

  * cart line item options
  * order item options
  * emails/admin order item display (after quote→order conversion)

### 3\) Apply selected amount as price (after add)

* Event: `checkout\_cart\_product\_add\_after`
* Observer: `Observer/ApplyGiftCardAmountToQuoteItem.php`

Behavior:

* Converts posted `amount` into **base currency**
* Applies it as:

  * `quote\_item.custom\_price`
  * `quote\_item.original\_custom\_price`





## Applying an existing gift card code in cart/checkout (discount)

There are two UIs (cart and checkout) calling the same underlying quote manager.

### Cart UI (form post)

* Template: `view/frontend/templates/cart/giftcard.phtml`
* Layout: `view/frontend/layout/checkout\_cart\_index.xml`
* Controller:

  * Apply: `Controller/Cart/Apply.php` (`venbhas\_giftcard/cart/apply`)
  * Remove: `Controller/Cart/Remove.php` (`venbhas\_giftcard/cart/remove`)

### Checkout UI (Knockout component)

* Layout: `view/frontend/layout/checkout\_index\_index.xml`
* Component: `view/frontend/web/js/view/payment/giftcard.js`
* Template: `view/frontend/web/template/payment/giftcard.html`
* AJAX controllers:

  * Apply: `Controller/Checkout/Apply.php` (`venbhas\_giftcard/checkout/apply`)
  * Remove: `Controller/Checkout/Remove.php` (`venbhas\_giftcard/checkout/remove`)

### Underlying quote storage

Both cart and checkout call:

* `Model/Quote/GiftCardManager.php`

Storage on quote:

* `quote.venbhas\_giftcard\_codes` (CSV string of codes)

Important: Applying a code does **not** deduct balance immediately. It:

* validates + locks the code to a redeemer (first apply)
* triggers totals recollection so the discount is computed





## Validation when applying a gift card code 

## Main validator:

* `Model/Quote/GiftCardRedeemValidator.php`

`assertMayApply($quote, $code)` enforces:

* code exists in `venbhas\_giftcard\_code`
* `status = active`
* `balance\_amount > 0`
* redeemer lock rules:

  * if `redeemer\_customer\_id` is set → quote customer id must match
  * else if `redeemer\_email` is set → quote customer email must match

Locking (first user wins):

* When a code has no lock yet, validator sets exactly one of:

  * `venbhas\_giftcard\_code.redeemer\_customer\_id`
  * `venbhas\_giftcard\_code.redeemer\_email`
* Update is guarded so races do not overwrite existing locks.

Practical effect:

* Once someone applies a code first time, other customers cannot apply it later.





## Totals: 

## Quote total collector:

* `Model/Total/Quote/GiftCard.php`

  * total code: `venbhas\_giftcard`

What it does:

* reads codes from quote (`GiftCardManager::getCodes()`)
* loads matching code rows from `venbhas\_giftcard\_code`
* skips codes that fail `GiftCardRedeemValidator::canQuoteUseGiftCard()`
* applies gift cards up to the quote base grand total
* stores:

  * `quote.venbhas\_giftcard\_amount`
  * `quote.base\_venbhas\_giftcard\_amount`
  * `quote.venbhas\_giftcard\_applied` (JSON array of `{code, base\_amount}`)
  * `quote.venbhas\_giftcard\_balance\_details` (JSON) used only for UI display

Copy quote → order (persistence):

* Observer: `Observer/ConvertQuoteToOrder.php` on `sales\_model\_service\_quote\_submit\_before`
* Copies to `sales\_order`:

  * `venbhas\_giftcard\_amount`, `base\_venbhas\_giftcard\_amount`
  * `venbhas\_giftcard\_codes` (CSV)
  * `venbhas\_giftcard\_applied` (JSON)
  * `venbhas\_giftcard\_usage\_details` (mirror of applied JSON for display)

Checkout totals API / JS “extra fields”:

* `etc/extension\_attributes.xml` extends `Magento\\Quote\\Api\\Data\\TotalsInterface` with:

  * `venbhas\_giftcard\_codes`, `venbhas\_giftcard\_balance\_details`
* Plugin: `Plugin/Quote/CartTotalRepositoryPlugin.php`

  * forces `collectTotals()`
  * exposes the two fields via `TotalsInterface.extension\_attributes`







## Database: 

## Defined in `etc/db\_schema.xml`.

### Gift card code table

`venbhas\_giftcard\_code` holds the gift card itself:

* `code` (unique)
* `status`: pending / active / inactive
* `balance\_amount`, `initial\_value`, `currency\_code`
* redeemer lock:

  * `redeemer\_customer\_id`, `redeemer\_email`
* purchase context:

  * `order\_id`, `order\_item\_id`, `product\_id`, `customer\_id` (purchaser)
* recipient/sender/delivery fields (including physical address)

### Gift card ledger / transactions table

`venbhas\_giftcard\_transaction` is the audit/ledger:

* `action`:

  * `checkout\_apply` (logged at order placement)
  * `redeem` (balance deduction on invoice pay)
* `amount`, `balance\_after`
* links: `order\_id`, `invoice\_id`, `customer\_id`, `customer\_email`

### Quote + Order columns

On `quote`:

* `venbhas\_giftcard\_amount`, `base\_venbhas\_giftcard\_amount`
* `venbhas\_giftcard\_codes` (CSV)
* `venbhas\_giftcard\_applied` (JSON)

On `sales\_order`:

* same as quote plus:

  * `venbhas\_giftcard\_usage\_details` (JSON for display)
  * `venbhas\_giftcard\_redeemed` (flag; prevents double redemption)







## Gift card issuance: 

## Important: “Purchased gift card product” codes are issued on **invoice payment**, not at order placement.

### 1\) Reserve pending rows at order placement

* Observer: `Observer/ReserveGiftCardDetailsOnOrderPlace.php`
* Calls: `Model/GiftCardIssuer::reservePendingForOrder($order)`

Behavior:

* Inserts one row per gift card quantity into `venbhas\_giftcard\_code` as:

  * `status = pending`
  * `code = PENDING-{orderId}-{orderItemId}-{seq}` (placeholder)
  * `balance\_amount = 0`, `initial\_value = 0`
  * sender/recipient/delivery details captured from order item options

### 2\) Activate / generate real codes on invoice payment

* Event: `sales\_order\_invoice\_pay`
* Observer: `Observer/GenerateGiftCardOnInvoicePay.php`
* Calls: `GiftCardIssuer::issueForOrderItem($order, $orderItem, $qtyThisInvoice)`

Activation:

* Converts pending rows to active and sets:

  * real `code` from `Model/CodeGenerator.php`
  * `balance\_amount = initial\_value = basePrice` of the gift card item
  * `status = active`
* Retries if code collision happens (unique index on `code`)

Code generation format:

* `Model/CodeGenerator.php`

  * random alphabet: `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`
  * grouped as: `XXXX-XXXX-XXXX-XXXX`







## Redeem: 

## Redeem happens on **invoice payment**:

* Observer: `Observer/GenerateGiftCardOnInvoicePay.php`

  * calls `Model/GiftCardRedeemer::redeemOnInvoicePay($order, $invoice)`

Redeemer behavior (`Model/GiftCardRedeemer.php`):

* Reads `sales\_order.venbhas\_giftcard\_applied` JSON (`\[{code, base\_amount}]`)
* For each code:

  * loads gift card row with `FOR UPDATE`
  * validates:

    * exists, active
    * redeemer lock matches order customer/email
    * sufficient balance
  * updates `venbhas\_giftcard\_code.balance\_amount` (and status inactive if balance reaches 0)
  * inserts transaction row:

    * `action = redeem`, with `invoice\_id`
* Sets `sales\_order.venbhas\_giftcard\_redeemed = 1` (DB + runtime) so invoice pay cannot double-redeem.







## Ledger logging: 

## At order placement, the module logs a “checkout apply” ledger entry (audit trail).

* Observer: `Observer/LogGiftCardCheckoutUsageOnOrderPlace.php`
* Writer: `Model/GiftCardCheckoutTransactionLogger.php`

It inserts into `venbhas\_giftcard\_transaction`:

* `action = checkout\_apply`
* `amount = used amount for that order`
* `balance\_after` (best effort snapshot)

It does **not** change `balance\_amount`. Balance changes only in redeem flow (invoice pay).







## Emails: 

## Email templates are registered in:

* `etc/email\_templates.xml`

  * `venbhas\_giftcard\_email\_template` → `view/frontend/email/giftcard\_email.html`
  * `venbhas\_giftcard\_redemption\_receipt\_template` → `view/frontend/email/giftcard\_redemption\_receipt.html`

### A) “You received a Gift Card” (recipient email)

* Sender: `Model/Email/GiftCardSender.php`
* Trigger: invoice payment observer `Observer/GenerateGiftCardOnInvoicePay.php`

  * after issuing codes for each invoice item, it calls `$this->sender->send($code)`

Rules:

* If `delivery\_type` is **physical** → **skips email** (physical card is shipped; code is not emailed)
* Requires `recipient\_email`
* Template vars include:

  * gift card code, formatted value, sender/recipient names, message





### B) “Gift card usage receipt” (customer placing the order)

* Sender: `Model/Email/GiftCardRedemptionReceiptSender.php`
* Trigger: invoice payment observer `Observer/GenerateGiftCardOnInvoicePay.php`

  * always called after redeem attempt: `$this->redemptionReceiptSender->sendForOrder($order)`

Rules:

* Requires `order.customer\_email`
* Requires `sales\_order.venbhas\_giftcard\_applied` JSON
* Builds an HTML table including:

  * code, amount used, initial amount, current balance (read from `venbhas\_giftcard\_code`)







## “My codes” in checkout: 

## Checkout component loads “previously used gift cards”:

* JS: `view/frontend/web/js/view/payment/giftcard.js`

  * calls `GET venbhas\_giftcard/checkout/mycodes` for logged-in customers

Controller:

* `Controller/Checkout/MyCodes.php`

Query:

* selects active codes where:

  * `redeemer\_customer\_id = current customer`
  * `status = active`
  * `balance\_amount > 0`
* returns up to 25 rows: `{code, amount, currency}`

Meaning:

* This feature lists codes **locked to the customer** (first-applied redeemer), not codes they purchased.

\---

## My Account: 

## Navigation items are always added by:

* `view/frontend/layout/customer\_account.xml`

  * “My Gift Cards” → `venbhas\_giftcard/account/index`
  * “My Gift Card Transactions” → `venbhas\_giftcard/account/transactions`

### A) “My Gift Cards” 

* Controller: `Controller/Account/Index.php`
* Layout: `view/frontend/layout/venbhas\_giftcard\_account\_index.xml`
* Block: `Block/Account/MyGiftCards.php`
* Template: `view/frontend/templates/account/mygiftcards.phtml`

Filter logic:

* Lists gift cards where the customer is the **redeemer**:

  * `redeemer\_customer\_id = current customer id`
  * OR `redeemer\_email = customer email` (lowercased match)

So:

* A gift card you received by email will appear here **only after you apply it** (because applying is when the lock is set).
* It is intentionally **not** a “gift cards I purchased” list.
* 

### B) “My Gift Card Transactions” 

* Controller: `Controller/Account/Transactions.php`
* Layout: `view/frontend/layout/venbhas\_giftcard\_account\_transactions.xml`
* Block: `Block/Account/GiftCardTransactions.php`
* Template: `view/frontend/templates/account/transactions.phtml`

Data source:

* `Model/CustomerGiftCardTransactionsLoader.php` builds a collection:

  * base: `Model/ResourceModel/GiftCardTransaction/Collection.php`
  * joins:

    * gift card code table (code + currency): `joinGiftCardCode()`
    * sales order table (increment id): `joinSalesOrder()`
  * filters actions:

    * `checkout\_apply`, `redeem`
  * filters “my rows” by:

    * transaction `customer\_id` or `customer\_email`, OR joined `sales\_order.customer\_id`

Displayed columns:

* date, type (action label), order increment link, gift card code, amount used, balance after.





## 

