<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;

class GiftCardRedeemer
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Json $json
    ) {
    }

    /**
     * Deduct balances for gift cards applied to an order; record transactions; lock card to redeemer on first use.
     */
    public function redeemOnInvoicePay(OrderInterface $order, InvoiceInterface $invoice): void
    {
        if ((int) $order->getData('venbhas_giftcard_redeemed') === 1) {
            return;
        }

        $raw = (string) $order->getData('venbhas_giftcard_applied');
        if ($raw === '') {
            $order->setData('venbhas_giftcard_redeemed', 1);

            return;
        }

        $applied = $this->decodeApplied($raw);
        if (!$applied) {
            $order->setData('venbhas_giftcard_redeemed', 1);

            return;
        }

        $conn = $this->resource->getConnection();
        $codeTable = $this->resource->getTableName('venbhas_giftcard_code');
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');

        $orderCustomerId = $order->getCustomerId() ? (int) $order->getCustomerId() : null;
        $orderEmail = strtolower(trim((string) $order->getCustomerEmail()));

        $conn->beginTransaction();
        try {
            foreach ($applied as $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $amount = (float) ($row['base_amount'] ?? 0);
                if ($code === '' || $amount <= 0.0001) {
                    continue;
                }

                $select = $conn->select()
                    ->from($codeTable, [
                        'entity_id',
                        'balance',
                        'status',
                        'redeemer_customer_id',
                        'redeemer_email',
                    ])
                    ->where('code = ?', $code)
                    ->forUpdate(true);
                $gc = $conn->fetchRow($select);
                if (!$gc) {
                    throw new LocalizedException(__('Gift card code %1 was not found.', $code));
                }
                if ((int) $gc['status'] !== GiftCardCode::STATUS_ACTIVE) {
                    throw new LocalizedException(__('Gift card code %1 is not active.', $code));
                }

                $this->assertOrderMatchesRedeemerLock($order, $gc);

                $balance = (float) $gc['balance'];
                if ($balance + 0.0001 < $amount) {
                    throw new LocalizedException(__('Gift card code %1 has insufficient balance.', $code));
                }

                $newBalance = max(0.0, $balance - $amount);
                $newStatus = $newBalance <= 0.0001 ? GiftCardCode::STATUS_INACTIVE : GiftCardCode::STATUS_ACTIVE;

                $update = [
                    'balance' => $newBalance,
                    'status' => $newStatus,
                ];

                $hasLock = !empty($gc['redeemer_customer_id']) || !empty($gc['redeemer_email']);
                if (!$hasLock) {
                    if ($orderCustomerId) {
                        $update['redeemer_customer_id'] = $orderCustomerId;
                    } elseif ($orderEmail !== '') {
                        $update['redeemer_email'] = $orderEmail;
                    }
                }

                $conn->update(
                    $codeTable,
                    $update,
                    ['entity_id = ?' => (int) $gc['entity_id']]
                );

                $conn->insert($trxTable, [
                    'giftcard_id' => (int) $gc['entity_id'],
                    'action' => 'redeem',
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                    'order_id' => (int) $order->getEntityId() ?: null,
                    'invoice_id' => (int) $invoice->getEntityId() ?: null,
                    'creditmemo_id' => null,
                    'customer_id' => $orderCustomerId,
                    'customer_email' => $order->getCustomerEmail(),
                ]);
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $order->setData('venbhas_giftcard_redeemed', 1);
    }

    /**
     * @param array<string, mixed> $gc
     */
    private function assertOrderMatchesRedeemerLock(OrderInterface $order, array $gc): void
    {
        $lockedId = isset($gc['redeemer_customer_id']) ? (int) $gc['redeemer_customer_id'] : 0;
        $lockedEmail = strtolower(trim((string) ($gc['redeemer_email'] ?? '')));
        if ($lockedId <= 0 && $lockedEmail === '') {
            return;
        }
        $orderCustomerId = $order->getCustomerId() ? (int) $order->getCustomerId() : null;
        $orderEmail = strtolower(trim((string) $order->getCustomerEmail()));

        if ($lockedId > 0) {
            if ((int) $orderCustomerId !== $lockedId) {
                throw new LocalizedException(
                    __('Gift card redemption does not match the customer account linked to this card.')
                );
            }

            return;
        }
        if ($orderEmail === '' || $orderEmail !== $lockedEmail) {
            throw new LocalizedException(
                __('Gift card redemption does not match the email linked to this card.')
            );
        }
    }

    /**
     * @return array<int, array{code: string, base_amount: float}>
     */
    private function decodeApplied(string $raw): array
    {
        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\Throwable) {
            $decoded = null;
        }
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
