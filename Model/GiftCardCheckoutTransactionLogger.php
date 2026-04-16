<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Records venbhas_giftcard_transaction rows when a gift card is applied at checkout (order placed).
 * Financial deduction still happens on invoice pay (see GiftCardRedeemer); this is the usage ledger for checkout.
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

    public function __construct(
        ResourceConnection $resource,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->json = $json;
        $this->logger = $logger;
    }

    public function logForOrder(OrderInterface $order): void
    {
        $orderId = (int)$order->getEntityId();
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

        $conn->beginTransaction();
        try {
            foreach ($applied as $row) {
                $code = strtoupper(trim((string)($row['code'] ?? '')));
                $amount = (float)($row['base_amount'] ?? $row['amount'] ?? 0);
                if ($code === '' || $amount <= 0.0001) {
                    continue;
                }

                $select = $conn->select()
                    ->from($codeTable, ['entity_id', 'balance_amount', 'amount', 'balance', 'status'])
                    ->where('code = ?', $code);
                $gc = $conn->fetchRow($select);
                if (!$gc) {
                    $this->logger->warning(
                        'Venbhas GiftCard: checkout transaction log skipped, code not found.',
                        ['order_id' => $orderId, 'code' => $code]
                    );
                    continue;
                }
                if ((int)$gc['status'] !== GiftCardCode::STATUS_ACTIVE) {
                    continue;
                }

                $currentAmount = (float)($gc['balance_amount'] ?? 0);
                if ($currentAmount <= 0.0001) {
                    // Backward-compat: older installs may use column name "amount" or "balance".
                    $currentAmount = (float)($gc['amount'] ?? 0);
                    if ($currentAmount <= 0.0001) {
                        $currentAmount = (float)($gc['balance'] ?? 0);
                    }
                }
                $usedAmount = min($amount, $currentAmount);
                $balanceAfter = max(0.0, $currentAmount - $usedAmount);

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
}
