<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Persist gift card totals / codes from quote to order (same pattern as GiftDelivery sample).
 */
class ConvertQuoteToOrder implements ObserverInterface
{
    private const FIELDS = [
        'venbhas_giftcard_amount',
        'base_venbhas_giftcard_amount',
        'venbhas_giftcard_codes',
        'venbhas_giftcard_applied',
        'venbhas_giftcard_usage_details',
    ];

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();
        if (!$order || !$quote) {
            return;
        }

        foreach (self::FIELDS as $field) {
            $order->setData($field, $quote->getData($field));
        }
    }
}
