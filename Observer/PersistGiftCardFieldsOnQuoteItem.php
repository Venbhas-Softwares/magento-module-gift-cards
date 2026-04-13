<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

class PersistGiftCardFieldsOnQuoteItem implements ObserverInterface
{
    public function __construct(
        private readonly Json $json,
        private readonly RequestInterface $request,
        private readonly GiftOptionsResolver $giftOptionsResolver
    ) {}

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

        $data = (array)$this->request->getParam('venbhas_giftcard', []);

        $mapped = [
            'amount' => isset($data['amount']) ? (string)(float)$data['amount'] : '',
            'recipient_name' => trim((string)($data['recipient_name'] ?? '')),
            'recipient_email' => trim((string)($data['recipient_email'] ?? '')),
            'sender_name' => trim((string)($data['sender_name'] ?? '')),
            'sender_email' => trim((string)($data['sender_email'] ?? '')),
            'message' => trim((string)($data['message'] ?? '')),
            'delivery_type' => trim((string)($data['delivery_type'] ?? '')),
        ];

        if ($this->giftOptionsResolver->isPhysicalDeliverySelected($data, $product, (int) $product->getStoreId())) {
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
                $decoded = $this->json->unserialize((string)$existing->getValue());
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
            $additional[] = [
                'label' => $label,
                'value' => $v,
                'option_code' => $k,
            ];
        }

        $quoteItem->addOption([
            'code' => 'additional_options',
            'value' => $this->json->serialize($additional),
        ]);
    }

    private function labelForGiftCardOptionKey(string $k): string
    {
        return match ($k) {
            'amount' => (string) __('Amount'),
            'delivery_street' => (string) __('Delivery street'),
            'delivery_city' => (string) __('Delivery city'),
            'delivery_region' => (string) __('Delivery state / province'),
            'delivery_postcode' => (string) __('Delivery ZIP / postal code'),
            'delivery_country' => (string) __('Delivery country'),
            default => ucwords(str_replace('_', ' ', $k)),
        };
    }
}
