<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

/**
 * Human-readable ledger descriptions for venbhas_giftcard_transaction rows.
 */
// phpcs:disable Magento2.Functions.StaticFunction -- description factory is intentionally static
class GiftCardTransactionDescription
{
    /** @deprecated Stored on legacy rows; kept for credit-back sum queries. */
    public const LEGACY_CANCEL = 'order canceled';

    /** @deprecated Prefix on legacy refund rows; kept for credit-back sum queries. */
    public const LEGACY_REFUND_PREFIX = 'order refunded';

    /**
     * Wallet debit when gift amount is applied at checkout.
     *
     * @param string $incrementId
     * @return string
     */
    public static function debitedForOrder(string $incrementId): string
    {
        return sprintf('Debited for #%s', self::normalizeOrderNumber($incrementId));
    }

    /**
     * Credit when a customer redeems a code from My Account.
     *
     * @param string $giftCardCode
     * @return string
     */
    public static function addedInAccount(string $giftCardCode): string
    {
        return sprintf(
            'Added a new giftcard (%s) in my account',
            self::normalizeGiftCardCode($giftCardCode)
        );
    }

    /**
     * Credit when a customer redeems a code during checkout.
     *
     * @param string $giftCardCode
     * @return string
     */
    public static function addedInCheckout(string $giftCardCode): string
    {
        return sprintf(
            'Added a new giftcard (%s) in checkout',
            self::normalizeGiftCardCode($giftCardCode)
        );
    }

    /**
     * Wallet credit when a gift-applied order is cancelled.
     *
     * @param string $incrementId
     * @return string
     */
    public static function creditedForCancelledOrder(string $incrementId): string
    {
        return sprintf(
            'Credited from the order #%s for cancelled order',
            self::normalizeOrderNumber($incrementId)
        );
    }

    /**
     * Wallet credit when gift amount is returned on refund.
     *
     * @param string $incrementId
     * @param int|null $creditmemoId When set, makes partial-refund rows idempotent per credit memo.
     * @return string
     */
    public static function creditedForRefund(string $incrementId, ?int $creditmemoId = null): string
    {
        $description = sprintf(
            'Credited from the order #%s for refund',
            self::normalizeOrderNumber($incrementId)
        );
        if ($creditmemoId !== null && $creditmemoId > 0) {
            $description .= sprintf(' (creditmemo %d)', $creditmemoId);
        }

        return $description;
    }

    /**
     * SQL LIKE pattern matching any wallet credit-back description for an order.
     */
    public static function creditBackLikePattern(): string
    {
        return 'Credited from the order %';
    }

    /**
     * Whether a stored description indicates a wallet debit (usage), not a credit.
     *
     * @param string $description
     * @return bool
     */
    public static function descriptionIndicatesDebit(string $description): bool
    {
        $normalized = mb_strtolower(trim($description));

        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, 'debited')) {
            return true;
        }

        if (str_starts_with($normalized, 'applied at checkout')) {
            return true;
        }

        return false;
    }

    /**
     * SQL condition matching debit descriptions (for balance queries / data fixes).
     *
     * @param string $descriptionColumn Qualified column name, e.g. main_table.description
     */
    public static function debitDescriptionSqlCondition(string $descriptionColumn): string
    {
        return '(' . $descriptionColumn . " LIKE 'Debited%' OR "
            . $descriptionColumn . " LIKE 'Applied at checkout%')";
    }

    /**
     * Normalize order increment ID for description text.
     *
     * @param string $incrementId
     * @return string
     */
    private static function normalizeOrderNumber(string $incrementId): string
    {
        return ltrim(trim($incrementId), '#');
    }

    /**
     * Normalize gift card code for description text.
     *
     * @param string $giftCardCode
     * @return string
     */
    private static function normalizeGiftCardCode(string $giftCardCode): string
    {
        return strtoupper(trim($giftCardCode));
    }
}
// phpcs:enable Magento2.Functions.StaticFunction
