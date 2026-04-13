<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\Email\GiftCardSender;
use Venbhas\GiftCard\Model\GiftCardIssuer;

/**
 * Generate gift card codes on order placement (so admin grid is populated immediately).
 * If you prefer generating only after invoice payment, disable this observer.
 */
class GenerateGiftCardOnOrderPlace implements ObserverInterface
{
    public function __construct(
        GiftCardIssuer $issuer,
        GiftCardSender $sender
    ) {
        $this->issuer = $issuer;
        $this->sender = $sender;
    }

    /**
     * @var GiftCardIssuer
     */
    private $issuer;

    /**
     * @var GiftCardSender
     */
    private $sender;

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface|null $order */
        $order = $observer->getData('order');
        if (!$order) {
            return;
        }

        foreach ($order->getAllItems() as $orderItem) {
            if (!$orderItem) {
                continue;
            }
            $codes = $this->issuer->issueForOrderItem($order, $orderItem);
            foreach ($codes as $code) {
                $this->sender->send($code);
            }
        }
    }
}

