<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Model\GiftCardCheckoutTransactionLogger;

/**
 * Observer to log gift card usage transactions when an order is placed.
 */
class LogGiftCardCheckoutUsageOnOrderPlace extends AbstractObserver
{
    /**
     * @var GiftCardCheckoutTransactionLogger
     */
    private GiftCardCheckoutTransactionLogger $_checkoutTransactionLogger;

    /**
     * Initialize observer.
     *
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param GiftCardCheckoutTransactionLogger $checkoutTransactionLogger Checkout transaction logger
     */
    public function __construct(
        ModuleEnabledGuard $moduleEnabledGuard,
        GiftCardCheckoutTransactionLogger $checkoutTransactionLogger
    ) {
        parent::__construct($moduleEnabledGuard);
        $this->_checkoutTransactionLogger = $checkoutTransactionLogger;
    }

    /**
     * @inheritdoc
     */
    protected function executeWhenEnabled(Observer $observer): void
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
