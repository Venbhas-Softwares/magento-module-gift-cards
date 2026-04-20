<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Observer to persist gift card form fields onto quote item additional options.
 */
class PersistGiftCardFieldsOnQuoteItem implements ObserverInterface
{
    /**
     * @var Json
     */
    private Json $_json;

    /**
     * @var RequestInterface
     */
    private RequestInterface $_request;

    /**
     * @var GiftOptionsResolver
     */
    private GiftOptionsResolver $_giftOptionsResolver;

    /**
     * Initialize observer.
     *
     * @param Json $json JSON serializer
     * @param RequestInterface $request Request
     * @param GiftOptionsResolver $giftOptionsResolver Gift options resolver
     */
    public function __construct(
        Json $json,
        RequestInterface $request,
        GiftOptionsResolver $giftOptionsResolver
    ) {
        $this->_json = $json;
        $this->_request = $request;
        $this->_giftOptionsResolver = $giftOptionsResolver;
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
        /** @var QuoteItem|null $quoteItem */
        $quoteItem = $observer->getData('quote_item');
        if (!$quoteItem) {
            return;
        }

        $product = $quoteItem->getProduct();
        if (!$product || (string)$product->getTypeId() !== GiftCard::TYPE_CODE) {
            return;
        }

        $data = (array)$this->_request->getParam('venbhas_giftcard', []);

        $mapped = [
            'amount' => isset($data['amount']) ? (string)(float)$data['amount'] : '',
            'recipient_name' => trim((string)($data['recipient_name'] ?? '')),
            'recipient_email' => trim((string)($data['recipient_email'] ?? '')),
            'sender_name' => trim((string)($data['sender_name'] ?? '')),
            'sender_email' => trim((string)($data['sender_email'] ?? '')),
            'message' => trim((string)($data['message'] ?? '')),
            'delivery_type' => trim((string)($data['delivery_type'] ?? '')),
        ];

        if ($this->_giftOptionsResolver->isPhysicalDeliverySelected($data, $product, (int) $product->getStoreId())) {
            $mapped['delivery_street'] = trim((string) ($data['delivery_street'] ?? ''));
            $mapped['delivery_city'] = trim((string) ($data['delivery_city'] ?? ''));
            $mapped['delivery_region'] = trim((string) ($data['delivery_region'] ?? ''));
            $mapped['delivery_postcode'] = trim((string) ($data['delivery_postcode'] ?? ''));
            $mapped['delivery_country'] = trim((string) ($data['delivery_country'] ?? ''));
        }

        $additional = [];
        $existing = $quoteItem->getOptionByCode('additional_options');
        if ($existing && $existing->getValue()) {
            try {
                $decoded = $this->_json->unserialize((string)$existing->getValue());
                if (is_array($decoded)) {
                    $additional = $decoded;
                }
            } catch (\Throwable) {
                $additional = [];
            }
        }

        foreach ($mapped as $k => $v) {
            if ($v === '') {
                continue;
            }
            $label = $this->labelForGiftCardOptionKey($k);
            $displayValue = $k === 'delivery_type'
                ? $this->formatDeliveryTypeForDisplay((string) $v)
                : (string) $v;
            $additional[] = [
                'label' => $label,
                'value' => $displayValue,
                'option_code' => $k,
            ];
        }

        $quoteItem->addOption([
            'code' => 'additional_options',
            'value' => $this->_json->serialize($additional),
        ]);
    }

    /**
     * Resolve a display label for a gift card option key.
     *
     * @param string $k Option key
     *
     * @return string
     */
    private function labelForGiftCardOptionKey(string $k): string
    {
        return match ($k) {
            'amount' => (string) __('Amount'),
            'delivery_type' => (string) __('Gift card delivery'),
            'delivery_street' => (string) __('Delivery street'),
            'delivery_city' => (string) __('Delivery city'),
            'delivery_region' => (string) __('Delivery state / province'),
            'delivery_postcode' => (string) __('Delivery ZIP / postal code'),
            'delivery_country' => (string) __('Delivery country'),
            default => ucwords(str_replace('_', ' ', $k)),
        };
    }

    /**
     * Human-readable delivery method for order/invoice emails and admin line items.
     *
     * @param string $raw Raw delivery type
     *
     * @return string
     */
    private function formatDeliveryTypeForDisplay(string $raw): string
    {
        switch (strtolower(trim($raw))) {
            case Config::GIFT_DELIVERY_PHYSICAL:
                return (string) __('Physical delivery (card shipped — no email with code)');
            case Config::GIFT_DELIVERY_VIRTUAL:
                return (string) __('Virtual delivery (code sent by email when invoiced)');
            default:
                return $raw;
        }
    }
}
