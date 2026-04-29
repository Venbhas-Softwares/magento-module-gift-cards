<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\Product\Type\GiftCard as GiftCardType;
use Venbhas\GiftCard\Model\WalletLedger;

class CreditWalletOnCreditmemoRefund implements ObserverInterface
{
    /**
     * @var WalletLedger
     */
    private $ledger;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param WalletLedger $ledger Wallet credit-back helper
     * @param ResourceConnection $resource Resource connection
     */
    public function __construct(WalletLedger $ledger, ResourceConnection $resource)
    {
        $this->ledger = $ledger;
        $this->resource = $resource;
    }

    /**
     * Credit wallet back and cancel refunded gift card product codes.
     *
     * @param Observer $observer Event observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var CreditmemoInterface|null $creditmemo */
        $creditmemo = $observer->getData('creditmemo') ?: $observer->getEvent()->getCreditmemo();
        if (!$creditmemo instanceof CreditmemoInterface) {
            return;
        }
        $order = $creditmemo->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $orderId = (int) $order->getEntityId();
        $this->cancelGiftCardCodesForRefundedGiftProduct($creditmemo, $orderId);

        // Collector will set this; fallback to order amount capped by creditmemo totals.
        $amount = (float) ($creditmemo->getData('base_venbhas_giftcard_amount') ?? 0);
        if ($amount <= 0.0001) {
            $orderAmount = (float) (
                $order->getData('base_venbhas_giftcard_amount')
                ?? $order->getData('venbhas_giftcard_amount')
                ?? 0
            );
            $amount = min($orderAmount, (float) $creditmemo->getBaseGrandTotal());
        }

        if ($amount <= 0.0001) {
            return;
        }

        $creditmemoId = (int) $creditmemo->getEntityId();
        $desc = $creditmemoId > 0
            ? sprintf('order refunded (creditmemo %d)', $creditmemoId)
            : 'order refunded';

        // Allow multiple partial refunds; ensure idempotency per creditmemo.
        if ($this->ledger->hasCreditForOrder($orderId, $desc)) {
            return;
        }

        $this->ledger->creditBackToWallet($order, $amount, $desc);
    }

    /**
     * When a gift card product is refunded, mark issued codes as cancelled.
     *
     * This prevents later use from checkout or My Account.
     *
     * @param CreditmemoInterface $creditmemo Credit memo
     * @param int $orderId Order ID
     *
     * @return void
     */
    private function cancelGiftCardCodesForRefundedGiftProduct(CreditmemoInterface $creditmemo, int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $hasGiftCardItems = false;
        foreach ($creditmemo->getItems() as $cmItem) {
            $orderItem = $cmItem->getOrderItem();
            if ($orderItem && (string) $orderItem->getProductType() === GiftCardType::TYPE_CODE) {
                $hasGiftCardItems = true;
                break;
            }
        }
        if (!$hasGiftCardItems) {
            return;
        }

        $conn = $this->resource->getConnection();
        $codeTable = $this->resource->getTableName('venbhas_giftcard_code');
        $columns = array_keys((array) $conn->describeTable($codeTable));

        $update = [];
        if (in_array('is_cancelled', $columns, true)) {
            $update['is_cancelled'] = 1;
        }
        // Backward compatibility for alternate spelling if present in some DBs.
        if (in_array('is_canceled', $columns, true)) {
            $update['is_canceled'] = 1;
        }
        if (!$update) {
            return;
        }

        $conn->update($codeTable, $update, ['order_id = ?' => $orderId]);
    }
}
