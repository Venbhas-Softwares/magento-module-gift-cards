<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\GiftCardCheckoutTransactionLogger;

/**
 * Observer to log gift card usage transactions when an order is placed.
 */
class LogGiftCardCheckoutUsageOnOrderPlace implements ObserverInterface
{
    /**
     * @var GiftCardCheckoutTransactionLogger
     */
    private GiftCardCheckoutTransactionLogger $_checkoutTransactionLogger;

    /**
     * Initialize observer.
     *
     * @param GiftCardCheckoutTransactionLogger $checkoutTransactionLogger Checkout transaction logger
     */
    public function __construct(
        GiftCardCheckoutTransactionLogger $checkoutTransactionLogger
    ) {
        $this->_checkoutTransactionLogger = $checkoutTransactionLogger;
    }

    /**
     * Execute observer.
     *
     * @param Observer $observer Observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getData('order');
        if (!$order) {
            $order = $observer->getEvent()->getOrder();
        }
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->_checkoutTransactionLogger->logForOrder($order);
    }
}
