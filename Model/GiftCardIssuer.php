<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode as GiftCardCodeResource;

class GiftCardIssuer
{
    public function __construct(
        private readonly GiftCardCodeFactory $giftCardCodeFactory,
        private readonly GiftCardCodeResource $giftCardCodeResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly CodeGenerator $codeGenerator,
        private readonly Json $json
    ) {}

    /**
     * Create gift card codes for a giftcard order item.
     * For v1: quantity N generates N codes with same recipient details.
     *
     * @return GiftCardCode[] created codes
     */
    public function issueForOrderItem(OrderInterface $order, OrderItemInterface $item): array
    {
        if ((string)$item->getProductType() !== \Venbhas\GiftCard\Model\Product\Type\GiftCard::TYPE_CODE) {
            return [];
        }

        $qty = (int)max(1, (float)$item->getQtyInvoiced() ?: (float)$item->getQtyOrdered());
        $value = (float)$item->getBasePrice();
        if ($value <= 0.0001) {
            throw new LocalizedException(__('Gift card value must be greater than 0.'));
        }

        $store = $this->storeManager->getStore((int)$order->getStoreId());
        $currency = (string)$order->getBaseCurrencyCode();

        $options = $item->getProductOptions() ?: [];
        $recipientEmail = $this->readOption($options, 'recipient_email');
        $recipientName = $this->readOption($options, 'recipient_name');
        $senderEmail = $this->readOption($options, 'sender_email');
        $senderName = $this->readOption($options, 'sender_name');
        $message = $this->readOption($options, 'message');
        $deliveryType = $this->readOption($options, 'delivery_type');
        $deliveryStreet = $this->readOption($options, 'delivery_street');
        $deliveryCity = $this->readOption($options, 'delivery_city');
        $deliveryRegion = $this->readOption($options, 'delivery_region');
        $deliveryPostcode = $this->readOption($options, 'delivery_postcode');
        $deliveryCountry = $this->readOption($options, 'delivery_country');

        $created = [];
        for ($i = 0; $i < $qty; $i++) {
            $created[] = $this->createOne(
                value: $value,
                currency: $currency,
                websiteId: (int)$store->getWebsiteId(),
                storeId: (int)$store->getId(),
                customerId: $order->getCustomerId() ? (int)$order->getCustomerId() : null,
                productId: (int)$item->getProductId() ?: null,
                orderId: (int)$order->getEntityId() ?: null,
                orderItemId: (int)$item->getItemId() ?: null,
                senderName: $senderName,
                senderEmail: $senderEmail,
                recipientName: $recipientName,
                recipientEmail: $recipientEmail,
                message: $message,
                deliveryType: $deliveryType,
                deliveryStreet: $deliveryStreet,
                deliveryCity: $deliveryCity,
                deliveryRegion: $deliveryRegion,
                deliveryPostcode: $deliveryPostcode,
                deliveryCountry: $deliveryCountry
            );
        }

        return $created;
    }

    private function createOne(
        float $value,
        string $currency,
        int $websiteId,
        int $storeId,
        ?int $customerId,
        ?int $productId,
        ?int $orderId,
        ?int $orderItemId,
        ?string $senderName,
        ?string $senderEmail,
        ?string $recipientName,
        ?string $recipientEmail,
        ?string $message,
        ?string $deliveryType,
        ?string $deliveryStreet,
        ?string $deliveryCity,
        ?string $deliveryRegion,
        ?string $deliveryPostcode,
        ?string $deliveryCountry
    ): GiftCardCode {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $giftCard = $this->giftCardCodeFactory->create();
            $giftCard->setData([
                'code' => $this->codeGenerator->generate(),
                'status' => GiftCardCode::STATUS_ACTIVE,
                'balance' => $value,
                'initial_value' => $value,
                'currency_code' => $currency,
                'website_id' => $websiteId,
                'store_id' => $storeId,
                'customer_id' => $customerId,
                'product_id' => $productId,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'recipient_name' => $recipientName,
                'recipient_email' => $recipientEmail,
                'message' => $message,
                'delivery_type' => $deliveryType,
                'delivery_street' => $deliveryStreet,
                'delivery_city' => $deliveryCity,
                'delivery_region' => $deliveryRegion,
                'delivery_postcode' => $deliveryPostcode,
                'delivery_country' => $deliveryCountry,
            ]);

            try {
                $this->giftCardCodeResource->save($giftCard);
                return $giftCard;
            } catch (AlreadyExistsException) {
                // Unique code collision, retry.
            }
        }
        throw new LocalizedException(__('Could not generate a unique gift card code.'));
    }

    private function readOption(array $options, string $code): ?string
    {
        foreach (['options', 'additional_options'] as $bucket) {
            if (!isset($options[$bucket])) {
                continue;
            }
            $entries = $options[$bucket];
            if (is_string($entries)) {
                try {
                    $decoded = $this->json->unserialize($entries);
                } catch (\Throwable) {
                    $decoded = json_decode($entries, true);
                }
                $entries = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                if (($opt['option_code'] ?? null) === $code) {
                    $val = trim((string)($opt['value'] ?? ''));
                    return $val !== '' ? $val : null;
                }
            }
        }
        return null;
    }
}

