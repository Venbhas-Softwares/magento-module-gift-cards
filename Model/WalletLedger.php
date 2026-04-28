<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Writes wallet ledger rows into venbhas_giftcard_transaction.
 */
class WalletLedger
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var CustomerGiftCardTransactionsLoader
     */
    private $walletLoader;

    public function __construct(
        ResourceConnection $resource,
        CustomerGiftCardTransactionsLoader $walletLoader
    ) {
        $this->resource = $resource;
        $this->walletLoader = $walletLoader;
    }

    public function hasCreditForOrder(int $orderId, string $description): bool
    {
        if ($orderId <= 0) {
            return false;
        }
        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');
        $cnt = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM ' . $trxTable . ' WHERE order_id = ? AND transaction_type = ? AND description = ?',
            [$orderId, GiftCardTransaction::ACTION_CREDIT, $description]
        );
        return $cnt > 0;
    }

    public function creditBackToWallet(OrderInterface $order, float $amount, string $description): void
    {
        $orderId = (int) $order->getEntityId();
        if ($orderId <= 0 || $amount <= 0.0001) {
            return;
        }

        $customerId = $order->getCustomerId() ? (int) $order->getCustomerId() : 0;
        $email = strtolower(trim((string) $order->getCustomerEmail()));
        $email = $email !== '' ? $email : null;

        $previous = $this->walletLoader->getWalletBalance($customerId, $email);
        $current = $previous + $amount;

        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');

        $conn->insert($trxTable, [
            'giftcard_id' => null,
            'transaction_type' => GiftCardTransaction::ACTION_CREDIT,
            'amount' => $amount,
            'previous_balance' => $previous,
            'current_balance' => $current,
            'description' => $description,
            'order_id' => $orderId,
            'customer_id' => $customerId > 0 ? $customerId : null,
            'customer_email' => $email,
            'store_id' => (int) ($order->getStoreId() ?: 0),
        ]);
    }
}

