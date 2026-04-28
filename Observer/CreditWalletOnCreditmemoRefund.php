<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\WalletLedger;

class CreditWalletOnCreditmemoRefund implements ObserverInterface
{
    /**
     * @var WalletLedger
     */
    private $ledger;

    public function __construct(WalletLedger $ledger)
    {
        $this->ledger = $ledger;
    }

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

        // Collector will set this; fallback to order amount capped by creditmemo totals.
        $amount = (float) ($creditmemo->getData('base_venbhas_giftcard_amount') ?? 0);
        if ($amount <= 0.0001) {
            $orderAmount = (float) ($order->getData('base_venbhas_giftcard_amount') ?? $order->getData('venbhas_giftcard_amount') ?? 0);
            $amount = min($orderAmount, (float) $creditmemo->getBaseGrandTotal());
        }

        if ($amount <= 0.0001) {
            return;
        }

        $orderId = (int) $order->getEntityId();
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
}

