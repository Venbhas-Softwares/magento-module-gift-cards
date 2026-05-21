<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Records wallet debit ledger rows when an order is placed.
 */
class GiftCardCheckoutTransactionLogger
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SalesOrderEntityIdResolver
     */
    private $salesOrderEntityIdResolver;

    /**
     * @param ResourceConnection $resource Resource connection
     * @param LoggerInterface $logger Logger
     * @param SalesOrderEntityIdResolver $salesOrderEntityIdResolver Order ID resolver
     */
    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger,
        SalesOrderEntityIdResolver $salesOrderEntityIdResolver
    ) {
        $this->resource = $resource;
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
        $totalUsed = (float) (
            $order->getData('base_venbhas_giftcard_amount')
            ?? $order->getData('venbhas_giftcard_amount')
            ?? 0
        );
        if ($totalUsed <= 0.0001) {
            return;
        }

        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');

        $existing = (int)$conn->fetchOne(
            'SELECT COUNT(*) FROM ' . $trxTable . ' WHERE order_id = ? AND transaction_type = ?',
            [$orderId, GiftCardTransaction::TYPE_DEBIT]
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
                'transaction_type' => GiftCardTransaction::TYPE_DEBIT,
                'amount' => $usedAmount,
                'previous_balance' => $previousBalance,
                'current_balance' => $currentBalance,
                'description' => GiftCardTransactionDescription::debitedForOrder(
                    (string) $order->getIncrementId()
                ),
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
     * Calculate wallet balance for the given customer identity.
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $conn DB adapter
     * @param string $trxTable Transaction table
     * @param int|null $customerId Customer ID
     * @param string|null $customerEmail Customer email
     *
     * @return float
     */
    private function getWalletBalance(
        \Magento\Framework\DB\Adapter\AdapterInterface $conn,
        string $trxTable,
        ?int $customerId,
        ?string $customerEmail
    ): float {
        $cid = $customerId ? (int) $customerId : 0;
        if ($cid <= 0) {
            return 0.0;
        }

        return GiftCardWalletBalanceCalculator::fetchBalance(
            $conn,
            $trxTable,
            $this->resource->getTableName('sales_order'),
            $cid,
            $customerEmail
        );
    }
}
