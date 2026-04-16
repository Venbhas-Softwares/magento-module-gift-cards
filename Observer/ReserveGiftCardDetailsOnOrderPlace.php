<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Venbhas\GiftCard\Model\GiftCardIssuer;

/**
 * Persists sender/recipient details into venbhas_giftcard_code as pending rows.
 * Real codes are generated when the invoice is paid.
 */
class ReserveGiftCardDetailsOnOrderPlace implements ObserverInterface
{
    public function __construct(
        private readonly GiftCardIssuer $giftCardIssuer
    ) {}

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface|null $order */
        $order = $observer->getData('order');
        if (!$order) {
            $order = $observer->getEvent()->getOrder();
        }
        if (!$order) {
            return;
        }

        // Quote submit success can pass a concrete Order model; normalize.
        if ($order instanceof Order) {
            // ok
        }

        $this->giftCardIssuer->reservePendingForOrder($order);
    }
}
