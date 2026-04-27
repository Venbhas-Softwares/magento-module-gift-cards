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

/**
 * Issues gift card codes and reserves pending gift card rows from order item options.
 */
class GiftCardIssuer
{
    /**
     * @var GiftCardCodeFactory
     */
    private $giftCardCodeFactory;

    /**
     * @var GiftCardCodeResource
     */
    private $giftCardCodeResource;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CodeGenerator
     */
    private $codeGenerator;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var GiftCardCodeCollectionFactory
     */
    private $giftCardCodeCollectionFactory;

    /**
     * Initialize issuer.
     *
     * @param GiftCardCodeFactory $giftCardCodeFactory Gift card code factory
     * @param GiftCardCodeResource $giftCardCodeResource Gift card code resource
     * @param StoreManagerInterface $storeManager Store manager
     * @param CodeGenerator $codeGenerator Code generator
     * @param Json $json JSON serializer
     * @param GiftCardCodeCollectionFactory $giftCardCodeCollectionFactory Gift card code collection factory
     */
    public function __construct(
        GiftCardCodeFactory $giftCardCodeFactory,
        GiftCardCodeResource $giftCardCodeResource,
        StoreManagerInterface $storeManager,
        CodeGenerator $codeGenerator,
        Json $json,
        GiftCardCodeCollectionFactory $giftCardCodeCollectionFactory
    ) {
        $this->giftCardCodeFactory = $giftCardCodeFactory;
        $this->giftCardCodeResource = $giftCardCodeResource;
        $this->storeManager = $storeManager;
        $this->codeGenerator = $codeGenerator;
        $this->json = $json;
        $this->giftCardCodeCollectionFactory = $giftCardCodeCollectionFactory;
    }

    /**
     * Legacy: previously used to insert pending rows at order placement.
     * Gift cards are now issued only on invoice payment via {@see issueForOrderItem()}.
     *
     * @param OrderInterface $order Order
     *
     * @return void
     */
    public function reservePendingForOrder(OrderInterface $order): void
    {
        // Intentionally disabled: this project does not create placeholder/pending rows on order placement.
        return;
    }

    /**
     * Create or activate gift card codes for a gift card order line invoiced in this payment.
     *
     * @param OrderInterface $order Order
     * @param OrderItemInterface $item Order item
     * @param int $qtyThisInvoice Quantity invoiced
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

        $store = $this->storeManager->getStore((int) $order->getStoreId());

        $orderId = (int) $order->getEntityId() ?: null;
        $storeId = (int) $store->getId();
        $customerId = $order->getCustomerId() ? (int) $order->getCustomerId() : null;
        $purchasedByEmail = trim((string) $order->getCustomerEmail());
        if ($purchasedByEmail === '') {
            $purchasedByEmail = null;
        }

        $recipientEmail = $this->readOption($options, 'recipient_email');
        $recipientName = $this->readOption($options, 'recipient_name');
        $senderName = $this->readOption($options, 'sender_name');
        $message = $this->readOption($options, 'message');
        $deliveryType = $this->readOption($options, 'delivery_type');
        $deliveryStreet = $this->readOption($options, 'delivery_street');
        $deliveryCity = $this->readOption($options, 'delivery_city');
        $deliveryRegion = $this->readOption($options, 'delivery_region');
        $deliveryPostcode = $this->readOption($options, 'delivery_postcode');
        $deliveryCountry = $this->readOption($options, 'delivery_country');

        $created = [];
        for ($i = 0; $i < $qtyThisInvoice; $i++) {
            $created[] = $this->createOne(
                value: $value,
                storeId: $storeId,
                customerId: $customerId,
                customerEmail: $purchasedByEmail,
                productId: (int) $item->getProductId() ?: null,
                orderId: $orderId,
                senderName: $senderName,
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

    /**
     * Check whether any gift card rows exist for a given order item.
     *
     * @param int $orderId Order ID
     * @param int $orderItemId Order item ID
     *
     * @return bool
     */
    private function hasAnyRowForOrderItem(int $orderId, int $orderItemId): bool
    {
        // Legacy (order_item_id column removed in this project schema).
        return false;
    }

    /**
     * Load pending gift card rows reserved for an order item.
     *
     * @param int|null $orderId Order ID
     * @param int|null $orderItemId Order item ID
     * @param int $limit Limit
     *
     * @return GiftCardCode[]
     */
    private function loadPendingForOrderItem(?int $orderId, ?int $orderItemId, int $limit): array
    {
        // Legacy (pending/status/order_item_id columns removed in this project schema).
        return [];
    }

    /**
     * Activate a reserved pending row by generating a real code and setting value/balance.
     *
     * @param GiftCardCode $giftCard Gift card code entity
     * @param float $value Initial value
     * @param string $currency Currency code
     *
     * @return GiftCardCode
     * @throws LocalizedException
     */
    private function activatePendingRow(GiftCardCode $giftCard, float $value, string $currency): GiftCardCode
    {
        // Legacy (pending/status/balance columns removed in this project schema).
        return $giftCard;
    }

    /**
     * Build a placeholder code for pending rows.
     *
     * @param int $orderId Order ID
     * @param int $orderItemId Order item ID
     * @param int $seq Sequence number
     *
     * @return string
     */
    private function buildPendingPlaceholderCode(int $orderId, int $orderItemId, int $seq): string
    {
        // Legacy (pending placeholder codes are not used in this project).
        return sprintf('PENDING-%d-%d-%d', $orderId, $orderItemId, $seq);
    }

    /**
     * Create and persist a gift card code entity.
     *
     * @param float $value Initial value
     * @param string $currency Currency code
     * @param int $websiteId Website ID
     * @param int $storeId Store ID
     * @param int|null $customerId Customer ID
     * @param int|null $productId Product ID
     * @param int|null $orderId Order ID
     * @param int|null $orderItemId Order item ID
     * @param string|null $senderName Sender name
     * @param string|null $senderEmail Sender email
     * @param string|null $recipientName Recipient name
     * @param string|null $recipientEmail Recipient email
     * @param string|null $message Message
     * @param string|null $deliveryType Delivery type
     * @param string|null $deliveryStreet Delivery street
     * @param string|null $deliveryCity Delivery city
     * @param string|null $deliveryRegion Delivery region
     * @param string|null $deliveryPostcode Delivery postcode
     * @param string|null $deliveryCountry Delivery country
     *
     * @return GiftCardCode
     * @throws LocalizedException
     */
    private function createOne(
        float $value,
        int $storeId,
        ?int $customerId,
        ?string $customerEmail,
        ?int $productId,
        ?int $orderId,
        ?string $senderName,
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
                'store_id' => $storeId,
                'purchased_by' => $customerId,
                'purchased_by_email' => $customerEmail,
                'product_id' => $productId,
                'order_id' => $orderId,
                'sender_name' => $senderName,
                'recipient_name' => $recipientName,
                'recipient_email' => $recipientEmail,
                'message' => $message,
                'delivery_type' => $deliveryType,
                'delivery_street' => $deliveryStreet,
                'delivery_city' => $deliveryCity,
                'delivery_region' => $deliveryRegion,
                'delivery_postcode' => $deliveryPostcode,
                'delivery_country' => $deliveryCountry,
                'amount' => $value,
            ]);

            try {
                $this->giftCardCodeResource->save($giftCard);
                return $giftCard;
            } catch (AlreadyExistsException) {
                continue;
            }
        }
        throw new LocalizedException(__('Could not generate a unique gift card code.'));
    }

    /**
     * Read a gift card option value from product options buckets.
     *
     * @param array $options Product options
     * @param string $code Option code
     *
     * @return string|null
     */
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
