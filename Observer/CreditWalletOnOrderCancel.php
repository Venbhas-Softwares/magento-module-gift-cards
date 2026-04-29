<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\WalletLedger;

class CreditWalletOnOrderCancel implements ObserverInterface
{
    /**
     * @var WalletLedger
     */
    private $ledger;

    /**
     * @param WalletLedger $ledger Wallet credit-back helper
     */
    public function __construct(WalletLedger $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Credit wallet back when a gift-applied order is canceled.
     *
     * @param Observer $observer Event observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface|null $order */
        $order = $observer->getData('order') ?: $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $amount = (float) (
            $order->getData('base_venbhas_giftcard_amount')
            ?? $order->getData('venbhas_giftcard_amount')
            ?? 0
        );
        if ($amount <= 0.0001) {
            return;
        }

        $orderId = (int) $order->getEntityId();
        $desc = 'order canceled';
        if ($this->ledger->hasCreditForOrder($orderId, $desc)) {
            return;
        }

        $this->ledger->creditBackToWallet($order, $amount, $desc);
    }
}
