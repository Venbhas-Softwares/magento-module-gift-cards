<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;

/**
 * Persist gift card wallet totals from quote to order.
 *
 * Values are stored on the quote during totals collection; they may also exist on the shipping
 * address after collect. mergeDataObjects(quote→order) only transfers OrderInterface fields,
 * so this observer must run on sales_model_service_quote_submit_before.
 */
class ConvertQuoteToOrder extends AbstractObserver
{
    private const FIELDS = [
        'venbhas_giftcard_amount',
        'base_venbhas_giftcard_amount',
    ];

    /**
     * @inheritdoc
     */
    protected function executeWhenEnabled(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $quote = $observer->getEvent()->getQuote();
        if (!$order || !$quote instanceof Quote) {
            return;
        }

        foreach (self::FIELDS as $field) {
            $value = $quote->getData($field);
            if ($this->isEmptyForCopy($field, $value) && !$quote->getIsVirtual()) {
                $shipping = $quote->getShippingAddress();
                if ($shipping) {
                    $fromAddress = $shipping->getData($field);
                    if (!$this->isEmptyForCopy($field, $fromAddress)) {
                        $value = $fromAddress;
                    }
                }
            }
            if ($this->isEmptyForCopy($field, $value)) {
                $value = null;
            }
            $order->setData($field, $value);
        }
    }

    /**
     * Check whether a value should be treated as empty for copying to order.
     *
     * @param string $field Field name
     * @param mixed $value Value
     *
     * @return bool
     */
    private function isEmptyForCopy(string $field, mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if ($value === '') {
            return true;
        }
        if (str_contains($field, '_amount') && is_numeric($value) && (float) $value <= 0.0001) {
            return true;
        }

        return false;
    }
}
