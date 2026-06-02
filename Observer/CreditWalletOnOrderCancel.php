<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Model\GiftCardTransactionDescription;
use Venbhas\GiftCard\Model\WalletLedger;

class CreditWalletOnOrderCancel extends AbstractObserver
{
    /**
     * @var WalletLedger
     */
    private $ledger;

    /**
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param WalletLedger $ledger Wallet credit-back helper
     */
    public function __construct(ModuleEnabledGuard $moduleEnabledGuard, WalletLedger $ledger)
    {
        parent::__construct($moduleEnabledGuard);
        $this->ledger = $ledger;
    }

    /**
     * Credit wallet back when a gift-applied order is canceled.
     *
     * @param Observer $observer Event observer
     *
     * @return void
     */
    protected function executeWhenEnabled(Observer $observer): void
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
        $desc = GiftCardTransactionDescription::creditedForCancelledOrder(
            (string) $order->getIncrementId()
        );
        if ($this->ledger->hasCreditForOrder($orderId, $desc)) {
            return;
        }

        $this->ledger->creditBackToWallet($order, $amount, $desc);
    }
}
