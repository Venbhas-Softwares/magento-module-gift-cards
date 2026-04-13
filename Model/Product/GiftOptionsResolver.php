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

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function getDeliveryType(Product $product, ?int $storeId = null): string
    {
        $v = trim((string) $product->getData(self::ATTR_DELIVERY_TYPE));
        if ($v !== '') {
            if (in_array($v, [Config::GIFT_DELIVERY_VIRTUAL, Config::GIFT_DELIVERY_PHYSICAL, Config::GIFT_DELIVERY_BOTH], true)) {
                return $v;
            }
        }
        return $this->config->getGiftDeliveryType($storeId);
    }

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

    public function isCustomMessageAllowed(Product $product, ?int $storeId = null): bool
    {
        $raw = $product->getData(self::ATTR_ALLOW_CUSTOM_MESSAGE);
        if ($raw === null || $raw === '') {
            return $this->config->isCustomMessageAllowedByDefault($storeId);
        }
        return (bool) (int) $raw;
    }

    public function getMinAmount(Product $product, ?int $storeId = null): float
    {
        return $this->config->getMinAmount($storeId);
    }

    public function getMaxAmount(Product $product, ?int $storeId = null): float
    {
        return $this->config->getMaxAmount($storeId);
    }

    public function isDeliveryChoiceOnStorefront(Product $product, ?int $storeId = null): bool
    {
        return $this->getDeliveryType($product, $storeId) === Config::GIFT_DELIVERY_BOTH;
    }

    public function isPhysicalDeliveryOnly(Product $product, ?int $storeId = null): bool
    {
        return $this->getDeliveryType($product, $storeId) === Config::GIFT_DELIVERY_PHYSICAL;
    }

    /**
     * Product can be shipped physically (physical-only or shopper may choose physical).
     */
    public function isPhysicalDeliveryOffered(Product $product, ?int $storeId = null): bool
    {
        $t = $this->getDeliveryType($product, $storeId);
        return in_array($t, [Config::GIFT_DELIVERY_PHYSICAL, Config::GIFT_DELIVERY_BOTH], true);
    }

    /**
     * Whether the posted storefront choice resolves to physical delivery (requires address).
     *
     * @param array<string, mixed> $gcData
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
