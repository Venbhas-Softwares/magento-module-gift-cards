<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Builds wallet balance SQL (credits minus debits) for venbhas_giftcard_transaction.
 */
// phpcs:disable Magento2.Functions.StaticFunction -- SQL builder utilities are intentionally static
class GiftCardWalletBalanceCalculator
{
    /**
     * CASE expression: signed contribution of one transaction row to wallet balance.
     *
     * @param string $typeColumn e.g. main_table.transaction_type
     * @param string $amountColumn e.g. main_table.amount
     * @param string $descriptionColumn e.g. main_table.description
     * @return string
     */
    public static function signedAmountCaseSql(
        string $typeColumn,
        string $amountColumn,
        string $descriptionColumn
    ): string {
        $credit = (int) GiftCardTransaction::TYPE_CREDIT;
        $debit = (int) GiftCardTransaction::TYPE_DEBIT;
        $debitByDescription = GiftCardTransactionDescription::debitDescriptionSqlCondition($descriptionColumn);
        $legacyDebitTypes = "'debit', 'redeem', 'checkout_apply'";

        $legacyDebitWhen = 'WHEN LOWER(CAST(' . $typeColumn . ' AS CHAR)) IN (' . $legacyDebitTypes . ')'
            . ' THEN -' . $amountColumn . ' ';

        return 'CASE '
            . 'WHEN ' . $typeColumn . ' = ' . $debit . ' THEN -' . $amountColumn . ' '
            . $legacyDebitWhen
            . 'WHEN ' . $typeColumn . ' = ' . $credit . ' AND ' . $debitByDescription . ' THEN -' . $amountColumn . ' '
            . 'WHEN ' . $typeColumn . ' = ' . $credit . ' THEN ' . $amountColumn . ' '
            . 'WHEN LOWER(CAST(' . $typeColumn . ' AS CHAR)) = \'credit\' THEN ' . $amountColumn . ' '
            . 'ELSE 0 END';
    }

    /**
     * Build customer ownership SQL (matches account transaction grid).
     *
     * @param AdapterInterface $conn
     * @param int $customerId
     * @param string|null $customerEmail
     * @return string
     */
    public static function customerOwnershipSql(
        AdapterInterface $conn,
        int $customerId,
        ?string $customerEmail = null
    ): string {
        $cid = (int) $customerId;
        $ownership = 'main_table.customer_id = ' . $cid . ' OR so.customer_id = ' . $cid;
        $email = $customerEmail !== null ? strtolower(trim($customerEmail)) : '';
        if ($email !== '') {
            $ownership .= ' OR LOWER(main_table.customer_email) = ' . $conn->quote($email);
        }

        return $ownership;
    }

    /**
     * Fetch wallet balance for a customer (matches transaction grid ownership rules).
     *
     * @param AdapterInterface $conn DB connection
     * @param string $trxTable Transaction table name
     * @param string $orderTable Sales order table name
     * @param int $customerId Customer ID
     * @param string|null $customerEmail Customer email
     * @return float
     */
    public static function fetchBalance(
        AdapterInterface $conn,
        string $trxTable,
        string $orderTable,
        int $customerId,
        ?string $customerEmail = null
    ): float {
        if ($customerId <= 0) {
            return 0.0;
        }

        $ownership = self::customerOwnershipSql($conn, $customerId, $customerEmail);
        $fromJoin =
            ' FROM ' . $trxTable . ' AS main_table '
            . 'LEFT JOIN ' . $orderTable . ' AS so ON main_table.order_id = so.entity_id '
            . 'WHERE (' . $ownership . ')';

        $latestBalance = $conn->fetchOne(
            // phpcs:ignore Magento2.SQL.RawQuery.RawQuery
            'SELECT main_table.current_balance' . $fromJoin
            . ' ORDER BY main_table.created_at DESC, main_table.entity_id DESC LIMIT 1'
        );
        if ($latestBalance !== false && $latestBalance !== null) {
            return max(0.0, (float) $latestBalance);
        }

        $signedCase = self::signedAmountCaseSql(
            'main_table.transaction_type',
            'main_table.amount',
            'main_table.description'
        );

        return (float) $conn->fetchOne(
            // phpcs:ignore Magento2.SQL.RawQuery.RawQuery
            'SELECT COALESCE(SUM(' . $signedCase . '), 0) ' . $fromJoin
        );
    }
}
// phpcs:enable Magento2.Functions.StaticFunction
