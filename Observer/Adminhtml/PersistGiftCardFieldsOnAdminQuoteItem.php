<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Observer\Adminhtml;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Persists gift card fields and applies custom price when product is added via admin order creation.
 */
class PersistGiftCardFieldsOnAdminQuoteItem implements ObserverInterface
{
    /**
     * @var Json
     */
    private Json $json;

    /**
     * @var GiftOptionsResolver
     */
    private GiftOptionsResolver $giftOptionsResolver;

    /**
     * @param Json $json
     * @param GiftOptionsResolver $giftOptionsResolver
     */
    public function __construct(
        Json $json,
        GiftOptionsResolver $giftOptionsResolver
    ) {
        $this->json = $json;
        $this->giftOptionsResolver = $giftOptionsResolver;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $items = $observer->getData('items');
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $quoteItem) {
            if (!$quoteItem instanceof QuoteItem) {
                continue;
            }

            $product = $quoteItem->getProduct();
            if (!$product || (string) $product->getTypeId() !== GiftCard::TYPE_CODE) {
                continue;
            }

            $data = $this->extractGiftCardData($quoteItem);
            if (empty($data)) {
                continue;
            }

            $existing = $quoteItem->getOptionByCode('additional_options');
            if ($existing && $existing->getValue()) {
                $decoded = [];
                try {
                    $decoded = $this->json->unserialize((string) $existing->getValue());
                } catch (\Throwable $e) {
                    $decoded = [];
                }
                if (is_array($decoded) && !empty($decoded)) {
                    continue;
                }
            }

            $this->persistFields($quoteItem, $product, $data);
            $this->applyAmount($quoteItem, $data);
        }
    }

    /**
     * Extract gift card data from the quote item's buy request.
     *
     * @param QuoteItem $quoteItem
     * @return array
     */
    private function extractGiftCardData(QuoteItem $quoteItem): array
    {
        $option = $quoteItem->getOptionByCode('info_buyRequest');
        if (!$option || !$option->getValue()) {
            return [];
        }

        try {
            $buyRequest = $this->json->unserialize((string) $option->getValue());
        } catch (\Throwable $e) {
            return [];
        }

        return isset($buyRequest['venbhas_giftcard']) && is_array($buyRequest['venbhas_giftcard'])
            ? $buyRequest['venbhas_giftcard']
            : [];
    }

    /**
     * Persist gift card fields as additional options on the quote item.
     *
     * @param QuoteItem $quoteItem
     * @param \Magento\Catalog\Model\Product $product
     * @param array $data
     * @return void
     */
    private function persistFields(QuoteItem $quoteItem, $product, array $data): void
    {
        $storeId = (int) $product->getStoreId();
        $deliveryType = trim((string) ($data['delivery_type'] ?? ''));
        if ($deliveryType === '') {
            $deliveryType = $this->giftOptionsResolver->getDeliveryType($product, $storeId);
        }

        $mapped = [
            //'amount' => isset($data['amount']) ? (string) (float) $data['amount'] : '',
            'delivery_type' => $deliveryType,
            'recipient_name' => trim((string) ($data['recipient_name'] ?? '')),
            'recipient_email' => trim((string) ($data['recipient_email'] ?? '')),
            'sender_name' => trim((string) ($data['sender_name'] ?? '')),
            'sender_email' => trim((string) ($data['sender_email'] ?? '')),
            'message' => trim((string) ($data['message'] ?? '')),
        ];

        if ($this->giftOptionsResolver->isPhysicalDeliverySelected($data, $product, $storeId)) {
            $mapped['delivery_street'] = trim((string) ($data['delivery_street'] ?? ''));
            $mapped['delivery_city'] = trim((string) ($data['delivery_city'] ?? ''));
            $mapped['delivery_region'] = trim((string) ($data['delivery_region'] ?? ''));
            $mapped['delivery_postcode'] = trim((string) ($data['delivery_postcode'] ?? ''));
            $mapped['delivery_country'] = trim((string) ($data['delivery_country'] ?? ''));
        }

        $additional = [];
        foreach ($mapped as $k => $v) {
            if ($v === '') {
                continue;
            }
            $additional[] = [
                'label' => $this->labelFor($k),
                'value' => $k === 'delivery_type' ? $this->formatDeliveryType($v) : $v,
                'option_code' => $k,
            ];
        }

        if (!empty($additional)) {
            $quoteItem->addOption([
                'code' => 'additional_options',
                'value' => $this->json->serialize($additional),
            ]);
        }
    }

    /**
     * Apply the gift card amount as a custom price on the quote item.
     *
     * @param QuoteItem $quoteItem
     * @param array $data
     * @return void
     */
    private function applyAmount(QuoteItem $quoteItem, array $data): void
    {
        $amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
        if ($amount <= 0.0001) {
            return;
        }

        $quoteItem->setCustomPrice($amount);
        $quoteItem->setOriginalCustomPrice($amount);
        $quoteItem->getProduct()->setIsSuperMode(true);
    }

    /**
     * Get human-readable label for a gift card field key.
     *
     * @param string $k
     * @return string
     */
    private function labelFor(string $k): string
    {
        return match ($k) {
            'amount' => (string) __('Amount'),
            'sender_name' => (string) __('Sender Name'),
            'sender_email' => (string) __('Sender Email'),
            'recipient_name' => (string) __('Recipient Name'),
            'recipient_email' => (string) __('Recipient Email'),
            'message' => (string) __('Message'),
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
     * Format delivery type value for display.
     *
     * @param string $raw
     * @return string
     */
    private function formatDeliveryType(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            Config::GIFT_DELIVERY_PHYSICAL => (string) __('Physical delivery (card shipped — no email with code)'),
            Config::GIFT_DELIVERY_VIRTUAL => (string) __('Virtual delivery (code sent by email when invoiced)'),
            default => $raw,
        };
    }
}
