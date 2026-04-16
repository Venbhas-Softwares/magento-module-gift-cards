<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\GiftCardCheckoutTransactionLogger;

class LogGiftCardCheckoutUsageOnOrderPlace implements ObserverInterface
{
    public function __construct(
        private readonly GiftCardCheckoutTransactionLogger $checkoutTransactionLogger
    ) {}

    public function execute(Observer $observer): void
    {
        $order = $observer->getData('order');
        if (!$order) {
            $order = $observer->getEvent()->getOrder();
        }
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->checkoutTransactionLogger->logForOrder($order);
    }
}
