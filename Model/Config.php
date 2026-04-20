<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'venbhas_giftcard/general/enabled';

    public const XML_PATH_TOTAL_TITLE = 'venbhas_giftcard/general/total_title';

    public const XML_PATH_ALLOW_CUSTOM_MESSAGE = 'venbhas_giftcard/general/allow_custom_message';

    public const XML_PATH_ALLOW_CUSTOM_AMOUNT = 'venbhas_giftcard/amount/allow_custom_amount';

    public const XML_PATH_AMOUNT_PRESETS = 'venbhas_giftcard/amount/presets';

    public const XML_PATH_AMOUNT_MIN = 'venbhas_giftcard/amount/min';

    public const XML_PATH_AMOUNT_MAX = 'venbhas_giftcard/amount/max';

    public const XML_PATH_GIFT_DELIVERY_TYPE = 'venbhas_giftcard/delivery/gift_delivery_type';

    public const GIFT_DELIVERY_VIRTUAL = 'virtual';

    public const GIFT_DELIVERY_PHYSICAL = 'physical';

    public const GIFT_DELIVERY_BOTH = 'both';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Initialize config.
     *
     * @param ScopeConfigInterface $scopeConfig Scope config
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check whether the module is enabled.
     *
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Store default for whether the gift message field is shown (product can override).
     *
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isCustomMessageAllowedByDefault(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ALLOW_CUSTOM_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * When false, shoppers may only pick a preset amount (no “Other amount”).
     *
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isCustomAmountAllowed(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ALLOW_CUSTOM_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Used as layout helper (checkout summary title); $store may be null|int|store object.
     *
     * @param mixed $store Store
     *
     * @return string
     */
    public function getTotalTitle($store = null): string
    {
        $storeId = $this->resolveStoreId($store);
        $title = (string) $this->scopeConfig->getValue(
            self::XML_PATH_TOTAL_TITLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $title !== '' ? $title : (string) __('Gift Card');
    }

    /**
     * Resolve a store ID from a mixed store reference.
     *
     * @param mixed $store Store
     *
     * @return int|null
     */
    private function resolveStoreId($store): ?int
    {
        if ($store === null) {
            return null;
        }
        if (is_int($store) || (is_string($store) && ctype_digit($store))) {
            return (int) $store;
        }
        if (is_object($store) && method_exists($store, 'getId')) {
            return (int) $store->getId();
        }
        return null;
    }

    /**
     * Store-level gift delivery mode: virtual, physical, or both (shopper selects).
     *
     * @param int|null $storeId Store ID
     *
     * @return string
     */
    public function getGiftDeliveryType(?int $storeId = null): string
    {
        $v = (string) $this->scopeConfig->getValue(
            self::XML_PATH_GIFT_DELIVERY_TYPE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (in_array($v, [self::GIFT_DELIVERY_VIRTUAL, self::GIFT_DELIVERY_PHYSICAL, self::GIFT_DELIVERY_BOTH], true)) {
            return $v;
        }
        return self::GIFT_DELIVERY_BOTH;
    }

    /**
     * Check whether the store is virtual delivery only.
     *
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isVirtualDeliveryOnly(?int $storeId = null): bool
    {
        return $this->getGiftDeliveryType($storeId) === self::GIFT_DELIVERY_VIRTUAL;
    }

    /**
     * Check whether the store is physical delivery only.
     *
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isPhysicalDeliveryOnly(?int $storeId = null): bool
    {
        return $this->getGiftDeliveryType($storeId) === self::GIFT_DELIVERY_PHYSICAL;
    }

    /**
     * Check whether the delivery choice is shown on storefront.
     *
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isDeliveryChoiceOnStorefront(?int $storeId = null): bool
    {
        return $this->getGiftDeliveryType($storeId) === self::GIFT_DELIVERY_BOTH;
    }

    /**
     * Get configured preset amounts.
     *
     * @param int|null $storeId Store ID
     *
     * @return float[]
     */
    public function getAmountPresets(?int $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(
            self::XML_PATH_AMOUNT_PRESETS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $out = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
            $v = (float) trim((string) $part);
            if ($v > 0) {
                $out[] = round($v, 2);
            }
        }
        return $out;
    }

    /**
     * Get minimum allowed amount.
     *
     * @param int|null $storeId Store ID
     *
     * @return float
     */
    public function getMinAmount(?int $storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_AMOUNT_MIN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 1.0;
    }

    /**
     * Get maximum allowed amount.
     *
     * @param int|null $storeId Store ID
     *
     * @return float
     */
    public function getMaxAmount(?int $storeId = null): float
    {
        $v = (float) $this->scopeConfig->getValue(
            self::XML_PATH_AMOUNT_MAX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $v > 0 ? $v : 10000.0;
    }
}
