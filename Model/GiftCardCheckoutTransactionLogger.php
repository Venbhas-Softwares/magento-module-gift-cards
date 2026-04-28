<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Records checkout_apply ledger rows when an order is placed (applied at checkout).
 * Balance deduction and redeem rows occur on invoice payment (GiftCardRedeemer).
 */
class GiftCardCheckoutTransactionLogger
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SalesOrderEntityIdResolver
     */
    private $salesOrderEntityIdResolver;

    /**
     * Initialize logger.
     *
     * @param ResourceConnection $resource Resource connection
     * @param Json $json JSON serializer
     * @param LoggerInterface $logger Logger
     * @param SalesOrderEntityIdResolver $salesOrderEntityIdResolver Order ID resolver
     */
    public function __construct(
        ResourceConnection $resource,
        Json $json,
        LoggerInterface $logger,
        SalesOrderEntityIdResolver $salesOrderEntityIdResolver
    ) {
        $this->resource = $resource;
        $this->json = $json;
        $this->logger = $logger;
        $this->salesOrderEntityIdResolver = $salesOrderEntityIdResolver;
    }

    /**
     * Log checkout apply transactions for an order.
     *
     * @param OrderInterface $order Order
     *
     * @return void
     */
    public function logForOrder(OrderInterface $order): void
    {
        $orderId = $this->salesOrderEntityIdResolver->resolve($order);
        if ($orderId < 1) {
            return;
        }

        // Wallet model: usage is stored as an amount on the order, not JSON-applied codes.
        $totalUsed = (float) ($order->getData('base_venbhas_giftcard_amount') ?? $order->getData('venbhas_giftcard_amount') ?? 0);
        if ($totalUsed <= 0.0001) {
            return;
        }

        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');

        $existing = (int)$conn->fetchOne(
            'SELECT COUNT(*) FROM ' . $trxTable . ' WHERE order_id = ? AND transaction_type IN(?, ?)',
            [$orderId, GiftCardTransaction::ACTION_DEBIT, GiftCardTransaction::ACTION_CHECKOUT_APPLY]
        );
        if ($existing > 0) {
            return;
        }

        $orderCustomerId = $order->getCustomerId() ? (int)$order->getCustomerId() : null;
        $orderEmail = trim((string)$order->getCustomerEmail());
        $customerEmail = $orderEmail !== '' ? $orderEmail : null;

        $conn->beginTransaction();
        try {
            $previousBalance = $this->getWalletBalance($conn, $trxTable, $orderCustomerId, $customerEmail);
            $usedAmount = min($totalUsed, max(0.0, $previousBalance));
            $currentBalance = max(0.0, $previousBalance - $usedAmount);

            $conn->insert($trxTable, [
                'giftcard_id' => null,
                'transaction_type' => GiftCardTransaction::ACTION_DEBIT,
                'amount' => $usedAmount,
                'previous_balance' => $previousBalance,
                'current_balance' => $currentBalance,
                'description' => 'debited gift amount at checkout',
                'order_id' => $orderId,
                'customer_id' => $orderCustomerId,
                'customer_email' => $customerEmail,
                'store_id' => (int) ($order->getStoreId() ?: 0),
            ]);
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            $this->logger->error(
                'Venbhas GiftCard: checkout transaction log failed: ' . $e->getMessage(),
                ['order_id' => $orderId, 'exception' => $e]
            );
        }
    }

    /**
     * Read the current balance from a gift card DB row.
     *
     * @param array $row Gift card DB row
     *
     * @return float
     */
    private function readGiftCardBalanceFromRow(array $row): float
    {
        if (array_key_exists('amount', $row) && $row['amount'] !== null && $row['amount'] !== '') {
            return (float)$row['amount'];
        }

        return 0.0;
    }

    // rowHasBalanceColumn removed: this project schema always uses `amount`.

    private function getWalletBalance(\Magento\Framework\DB\Adapter\AdapterInterface $conn, string $trxTable, ?int $customerId, ?string $customerEmail): float
    {
        $where = [];
        $cid = $customerId ? (int) $customerId : 0;
        if ($cid > 0) {
            $where[] = 'customer_id = ' . $cid;
        }
        $email = $customerEmail !== null ? strtolower(trim($customerEmail)) : '';
        if ($email !== '') {
            $where[] = 'customer_email = ' . $conn->quote($email);
        }
        if (!$where) {
            return 0.0;
        }

        $sql = 'SELECT COALESCE(SUM(CASE '
            . 'WHEN transaction_type = ' . $conn->quote(GiftCardTransaction::ACTION_CREDIT) . ' THEN amount '
            . 'WHEN transaction_type = ' . $conn->quote(GiftCardTransaction::ACTION_DEBIT) . ' THEN -amount '
            . 'WHEN transaction_type = ' . $conn->quote(GiftCardTransaction::ACTION_CHECKOUT_APPLY) . ' THEN -amount '
            . 'WHEN transaction_type = ' . $conn->quote(GiftCardTransaction::ACTION_REDEEM) . ' THEN -amount '
            . 'ELSE 0 END), 0) '
            . 'FROM ' . $trxTable . ' WHERE (' . implode(' OR ', $where) . ')';

        return (float) $conn->fetchOne($sql);
    }
}
