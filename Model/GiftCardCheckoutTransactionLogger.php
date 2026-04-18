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

    public function logForOrder(OrderInterface $order): void
    {
        $orderId = $this->salesOrderEntityIdResolver->resolve($order);
        if ($orderId < 1) {
            return;
        }

        $raw = (string)$order->getData('venbhas_giftcard_applied');
        if ($raw === '') {
            return;
        }

        $applied = $this->decodeApplied($raw);
        if ($applied === []) {
            return;
        }

        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');
        $codeTable = $this->resource->getTableName('venbhas_giftcard_code');

        $existing = (int)$conn->fetchOne(
            'SELECT COUNT(*) FROM ' . $trxTable . ' WHERE order_id = ? AND action = ?',
            [$orderId, GiftCardTransaction::ACTION_CHECKOUT_APPLY]
        );
        if ($existing > 0) {
            return;
        }

        $orderCustomerId = $order->getCustomerId() ? (int)$order->getCustomerId() : null;
        $orderEmail = trim((string)$order->getCustomerEmail());
        $customerEmail = $orderEmail !== '' ? $orderEmail : null;

        $codeTableColumns = array_keys((array)$conn->describeTable($codeTable));
        // Only select columns that exist: requesting `amount`/`balance` on installs that only have
        // `balance_amount` makes MySQL throw "Unknown column" and the whole log is rolled back.
        $selectCodeColumns = ['entity_id', 'status'];
        foreach (['balance_amount', 'amount', 'balance'] as $balanceCol) {
            if (in_array($balanceCol, $codeTableColumns, true)) {
                $selectCodeColumns[] = $balanceCol;
            }
        }

        $conn->beginTransaction();
        try {
            foreach ($applied as $row) {
                $code = strtoupper(trim((string)($row['code'] ?? '')));
                $amount = (float)($row['base_amount'] ?? $row['amount'] ?? 0);
                if ($code === '' || $amount <= 0.0001) {
                    continue;
                }

                $select = $conn->select()
                    ->from($codeTable, $selectCodeColumns)
                    ->where('code = ?', $code);
                $gc = $conn->fetchRow($select);
                if (!$gc) {
                    $this->logger->warning(
                        'Venbhas GiftCard: checkout transaction log skipped, code not found.',
                        ['order_id' => $orderId, 'code' => $code]
                    );
                    continue;
                }

                $currentAmount = $this->readGiftCardBalanceFromRow($gc);
                if (!$this->rowHasBalanceColumn($gc)) {
                    $usedAmount = $amount;
                    $balanceAfter = 0.0;
                } else {
                    $usedAmount = min($amount, max(0.0, $currentAmount));
                    $balanceAfter = max(0.0, $currentAmount - $usedAmount);
                }

                $conn->insert($trxTable, [
                    'giftcard_id' => (int)$gc['entity_id'],
                    'action' => GiftCardTransaction::ACTION_CHECKOUT_APPLY,
                    'amount' => $usedAmount,
                    'balance_after' => $balanceAfter,
                    'order_id' => $orderId,
                    'invoice_id' => null,
                    'creditmemo_id' => null,
                    'customer_id' => $orderCustomerId,
                    'customer_email' => $customerEmail,
                ]);
            }
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
     * @return array<int, array<string, mixed>>
     */
    private function decodeApplied(string $raw): array
    {
        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\Exception $e) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function readGiftCardBalanceFromRow(array $row): float
    {
        foreach (['balance_amount', 'amount', 'balance'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (float)$row[$key];
            }
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowHasBalanceColumn(array $row): bool
    {
        foreach (['balance_amount', 'amount', 'balance'] as $key) {
            if (array_key_exists($key, $row)) {
                return true;
            }
        }

        return false;
    }
}
