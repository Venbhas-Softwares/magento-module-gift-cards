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
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as GiftCardCodeCollectionFactory;

class GiftCardIssuer
{
    public function __construct(
        private readonly GiftCardCodeFactory $giftCardCodeFactory,
        private readonly GiftCardCodeResource $giftCardCodeResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly CodeGenerator $codeGenerator,
        private readonly Json $json,
        private readonly GiftCardCodeCollectionFactory $giftCardCodeCollectionFactory
    ) {}

    /**
     * On order placement: insert pending rows with sender/recipient details; placeholder code until invoice.
     */
    public function reservePendingForOrder(OrderInterface $order): void
    {
        $orderId = (int)$order->getEntityId();
        if ($orderId < 1) {
            return;
        }

        $store = $this->storeManager->getStore((int)$order->getStoreId());
        $currency = (string)$order->getBaseCurrencyCode();

        foreach ($order->getAllItems() as $item) {
            // Some setups store gift card data on parent/child items; be tolerant.
            $options = $item->getProductOptions() ?: [];
            $hasGiftOptions = $this->readOption($options, 'recipient_email') !== null
                || $this->readOption($options, 'sender_email') !== null
                || $this->readOption($options, 'amount') !== null;

            $productType = (string)($item->getProductType() ?: '');
            if ($productType !== \Venbhas\GiftCard\Model\Product\Type\GiftCard::TYPE_CODE && !$hasGiftOptions) {
                continue;
            }
            $orderItemId = (int)($item->getItemId() ?: $item->getId());
            if ($orderItemId < 1) {
                continue;
            }
            if ($this->hasAnyRowForOrderItem($orderId, $orderItemId)) {
                continue;
            }

            $qty = (int)max(1, (float)$item->getQtyOrdered());
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

            for ($seq = 0; $seq < $qty; $seq++) {
                $giftCard = $this->giftCardCodeFactory->create();
                $giftCard->setData([
                    'code' => $this->buildPendingPlaceholderCode($orderId, $orderItemId, $seq),
                    'status' => GiftCardCode::STATUS_PENDING,
                    'balance_amount' => 0.0,
                    'initial_value' => 0.0,
                    'currency_code' => $currency,
                    'website_id' => (int)$store->getWebsiteId(),
                    'store_id' => (int)$store->getId(),
                    'customer_id' => $order->getCustomerId() ? (int)$order->getCustomerId() : null,
                    'product_id' => (int)$item->getProductId() ?: null,
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
                $this->giftCardCodeResource->save($giftCard);
            }
        }
    }

    /**
     * Create or activate gift card codes for a gift card order line invoiced in this payment.
     *
     * @return GiftCardCode[] Codes to email (active, with real value)
     */
    public function issueForOrderItem(OrderInterface $order, OrderItemInterface $item, int $qtyThisInvoice): array
    {
        if ($qtyThisInvoice < 1) {
            return [];
        }
        $options = $item->getProductOptions() ?: [];
        $hasGiftOptions = $this->readOption($options, 'recipient_email') !== null
            || $this->readOption($options, 'sender_email') !== null
            || $this->readOption($options, 'amount') !== null;
        $productType = (string)($item->getProductType() ?: '');
        if ($productType !== \Venbhas\GiftCard\Model\Product\Type\GiftCard::TYPE_CODE && !$hasGiftOptions) {
            return [];
        }

        $value = (float)$item->getBasePrice();
        if ($value <= 0.0001) {
            throw new LocalizedException(__('Gift card value must be greater than 0.'));
        }

        $store = $this->storeManager->getStore((int)$order->getStoreId());
        $currency = (string)$order->getBaseCurrencyCode();

        $orderId = (int)$order->getEntityId() ?: null;
        $orderItemId = (int)($item->getItemId() ?: $item->getId()) ?: null;

        $pending = $this->loadPendingForOrderItem($orderId, $orderItemId, $qtyThisInvoice);
        $created = [];

        foreach ($pending as $giftCard) {
            $created[] = $this->activatePendingRow($giftCard, $value, $currency);
        }

        $remaining = $qtyThisInvoice - count($pending);
        if ($remaining > 0) {
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

            for ($i = 0; $i < $remaining; $i++) {
                $created[] = $this->createOne(
                    value: $value,
                    currency: $currency,
                    websiteId: (int)$store->getWebsiteId(),
                    storeId: (int)$store->getId(),
                    customerId: $order->getCustomerId() ? (int)$order->getCustomerId() : null,
                    productId: (int)$item->getProductId() ?: null,
                    orderId: $orderId,
                    orderItemId: $orderItemId,
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
        }

        return $created;
    }

    private function hasAnyRowForOrderItem(int $orderId, int $orderItemId): bool
    {
        $collection = $this->giftCardCodeCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        $collection->addFieldToFilter('order_item_id', $orderItemId);
        $collection->setPageSize(1);
        return (int)$collection->getSize() > 0;
    }

    /**
     * @return GiftCardCode[]
     */
    private function loadPendingForOrderItem(?int $orderId, ?int $orderItemId, int $limit): array
    {
        if (!$orderId || !$orderItemId || $limit < 1) {
            return [];
        }
        $collection = $this->giftCardCodeCollectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        $collection->addFieldToFilter('order_item_id', $orderItemId);
        $collection->addFieldToFilter('status', GiftCardCode::STATUS_PENDING);
        $collection->setOrder('entity_id', 'ASC');
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        return $collection->getItems();
    }

    private function activatePendingRow(GiftCardCode $giftCard, float $value, string $currency): GiftCardCode
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $giftCard->setData('code', $this->codeGenerator->generate());
            $giftCard->setData('status', GiftCardCode::STATUS_ACTIVE);
            $giftCard->setData('balance_amount', $value);
            $giftCard->setData('initial_value', $value);
            $giftCard->setData('currency_code', $currency);

            try {
                $this->giftCardCodeResource->save($giftCard);
                return $giftCard;
            } catch (AlreadyExistsException) {
                // Unique code collision, retry.
            }
        }
        throw new LocalizedException(__('Could not generate a unique gift card code.'));
    }

    private function buildPendingPlaceholderCode(int $orderId, int $orderItemId, int $seq): string
    {
        return sprintf('PENDING-%d-%d-%d', $orderId, $orderItemId, $seq);
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
                'balance_amount' => $value,
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
        // Common: saved under buyRequest (info_buyRequest -> venbhas_giftcard).
        $buyRequest = $options['info_buyRequest'] ?? null;
        if (is_string($buyRequest) && $buyRequest !== '') {
            try {
                $decoded = $this->json->unserialize($buyRequest);
            } catch (\Throwable) {
                $decoded = json_decode($buyRequest, true);
            }
            $buyRequest = is_array($decoded) ? $decoded : null;
        }
        if (is_array($buyRequest)) {
            $gc = $buyRequest['venbhas_giftcard'] ?? null;
            if (is_string($gc) && $gc !== '') {
                try {
                    $decoded = $this->json->unserialize($gc);
                } catch (\Throwable) {
                    $decoded = json_decode($gc, true);
                }
                $gc = is_array($decoded) ? $decoded : null;
            }
            if (is_array($gc) && array_key_exists($code, $gc)) {
                $val = trim((string)($gc[$code] ?? ''));
                return $val !== '' ? $val : null;
            }
        }

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
