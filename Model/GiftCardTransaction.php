<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\Model\AbstractModel;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction as GiftCardTransactionResource;

/**
 * Gift card transaction ledger model.
 */
// phpcs:disable Magento2.Functions.StaticFunction -- type helpers are intentionally static
class GiftCardTransaction extends AbstractModel
{
    /** Wallet credit (adds to balance). */
    public const TYPE_CREDIT = 0;

    /** Wallet debit (subtracts from balance). */
    public const TYPE_DEBIT = 1;

    /** @deprecated Use TYPE_CREDIT */
    public const ACTION_CREDIT = self::TYPE_CREDIT;

    /** @deprecated Use TYPE_DEBIT */
    public const ACTION_DEBIT = self::TYPE_DEBIT;

    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(GiftCardTransactionResource::class);
    }

    /**
     * Whether the transaction type increases wallet balance.
     *
     * @param int|string|null $type Stored transaction_type value
     * @param string|null $description Ledger description (used when type was migrated incorrectly)
     */
    public static function isCredit(int|string|null $type, ?string $description = null): bool
    {
        return !self::isDebit($type, $description);
    }

    /**
     * Whether the transaction type decreases wallet balance.
     *
     * @param int|string|null $type Stored transaction_type value
     * @param string|null $description Ledger description (used when type was migrated incorrectly)
     */
    public static function isDebit(int|string|null $type, ?string $description = null): bool
    {
        if ($type !== null && $type !== '' && is_numeric($type)) {
            if ((int) $type === self::TYPE_DEBIT) {
                return true;
            }
            if ((int) $type === self::TYPE_CREDIT
                && $description !== null
                && GiftCardTransactionDescription::descriptionIndicatesDebit($description)
            ) {
                return true;
            }

            return false;
        }

        if ($type !== null && $type !== '') {
            $normalized = mb_strtolower((string) $type);

            return in_array($normalized, ['debit', 'redeem', 'checkout_apply'], true);
        }

        return $description !== null && GiftCardTransactionDescription::descriptionIndicatesDebit($description);
    }
}
// phpcs:enable Magento2.Functions.StaticFunction
