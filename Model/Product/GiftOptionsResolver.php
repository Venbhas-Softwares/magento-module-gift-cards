<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Product;

use Magento\Catalog\Model\Product;
use Venbhas\GiftCard\Model\Config;

/**
 * Resolves gift-card behaviour from product attributes, falling back to store configuration.
 */
class GiftOptionsResolver
{
    public const ATTR_DELIVERY_TYPE = 'venbhas_gc_delivery_type';

    public const ATTR_ALLOW_CUSTOM_AMOUNT = 'venbhas_gc_allow_custom_amount';

    public const ATTR_AMOUNTS_AVAILABLE = 'venbhas_gc_amounts_available';

    public const ATTR_ALLOW_CUSTOM_MESSAGE = 'venbhas_gc_allow_custom_message';

    /**
     * Initialize resolver.
     *
     * @param Config $config Module config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Get effective delivery type (product override or store config).
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return string
     */
    public function getDeliveryType(Product $product, ?int $storeId = null): string
    {
        $v = trim((string) $product->getData(self::ATTR_DELIVERY_TYPE));
        if ($v !== '') {
            if (in_array(
                $v,
                [Config::GIFT_DELIVERY_VIRTUAL, Config::GIFT_DELIVERY_PHYSICAL, Config::GIFT_DELIVERY_BOTH],
                true
            )) {
                return $v;
            }
        }
        return $this->config->getGiftDeliveryType($storeId);
    }

    /**
     * Check whether custom amount is allowed (product override or store config).
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isCustomAmountAllowed(Product $product, ?int $storeId = null): bool
    {
        $raw = $product->getData(self::ATTR_ALLOW_CUSTOM_AMOUNT);
        if ($raw === null || $raw === '') {
            return $this->config->isCustomAmountAllowed($storeId);
        }
        return (bool) (int) $raw;
    }

    /**
     * Preset amounts for this product; empty string / null falls back to store config list.
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return float[]
     */
    public function getAmountPresets(Product $product, ?int $storeId = null): array
    {
        $raw = trim((string) $product->getData(self::ATTR_AMOUNTS_AVAILABLE));
        if ($raw === '') {
            return $this->config->getAmountPresets($storeId);
        }
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
     * Check whether custom message is allowed (product override or store config).
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isCustomMessageAllowed(Product $product, ?int $storeId = null): bool
    {
        $raw = $product->getData(self::ATTR_ALLOW_CUSTOM_MESSAGE);
        if ($raw === null || $raw === '') {
            return $this->config->isCustomMessageAllowedByDefault($storeId);
        }
        return (bool) (int) $raw;
    }

    /**
     * Get minimum allowed amount.
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return float
     */
    public function getMinAmount(Product $product, ?int $storeId = null): float
    {
        return $this->config->getMinAmount($storeId);
    }

    /**
     * Get maximum allowed amount.
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return float
     */
    public function getMaxAmount(Product $product, ?int $storeId = null): float
    {
        return $this->config->getMaxAmount($storeId);
    }

    /**
     * Check whether delivery choice is available on storefront.
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isDeliveryChoiceOnStorefront(Product $product, ?int $storeId = null): bool
    {
        return $this->getDeliveryType($product, $storeId) === Config::GIFT_DELIVERY_BOTH;
    }

    /**
     * Check whether delivery is physical only.
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isPhysicalDeliveryOnly(Product $product, ?int $storeId = null): bool
    {
        return $this->getDeliveryType($product, $storeId) === Config::GIFT_DELIVERY_PHYSICAL;
    }

    /**
     * Product can be shipped physically (physical-only or shopper may choose physical).
     *
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isPhysicalDeliveryOffered(Product $product, ?int $storeId = null): bool
    {
        $t = $this->getDeliveryType($product, $storeId);
        return in_array($t, [Config::GIFT_DELIVERY_PHYSICAL, Config::GIFT_DELIVERY_BOTH], true);
    }

    /**
     * Whether the posted storefront choice resolves to physical delivery (requires address).
     *
     * @param array $gcData Gift card request data
     * @param Product $product Product
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isPhysicalDeliverySelected(array $gcData, Product $product, ?int $storeId = null): bool
    {
        $t = $this->getDeliveryType($product, $storeId);
        if ($t === Config::GIFT_DELIVERY_PHYSICAL) {
            return true;
        }
        if ($t === Config::GIFT_DELIVERY_BOTH) {
            $posted = isset($gcData['delivery_type']) ? trim((string) $gcData['delivery_type']) : '';
            return $posted === Config::GIFT_DELIVERY_PHYSICAL;
        }
        return false;
    }
}
