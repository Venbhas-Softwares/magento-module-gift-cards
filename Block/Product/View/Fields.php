<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Product\View;

use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Product view block for rendering gift card option fields.
 */
class Fields extends Template
{
    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var GiftOptionsResolver
     */
    private GiftOptionsResolver $giftOptionsResolver;

    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * Initialize block.
     *
     * @param Context $context Block context
     * @param Registry $registry Core registry
     * @param Config $config Module config
     * @param GiftOptionsResolver $giftOptionsResolver Gift options resolver
     * @param PriceCurrencyInterface $priceCurrency Price currency formatter
     * @param array $data Additional data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Config $config,
        GiftOptionsResolver $giftOptionsResolver,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->config = $config;
        $this->giftOptionsResolver = $giftOptionsResolver;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Get the current product from registry.
     *
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    /**
     * Check whether gift card fields should be rendered.
     *
     * @return bool
     */
    public function shouldRender(): bool
    {
        if (!$this->config->isEnabled((int) $this->_storeManager->getStore()->getId())) {
            return false;
        }
        $p = $this->getProduct();
        return $p && (string) $p->getTypeId() === GiftCard::TYPE_CODE;
    }

    /**
     * Check whether custom amount is allowed.
     *
     * @return bool
     */
    public function isCustomAmountAllowed(): bool
    {
        $p = $this->getProduct();
        if (!$p) {
            return $this->config->isCustomAmountAllowed((int) $this->_storeManager->getStore()->getId());
        }
        return $this->giftOptionsResolver->isCustomAmountAllowed($p, (int) $this->_storeManager->getStore()->getId());
    }

    /**
     * Check whether custom message is allowed.
     *
     * @return bool
     */
    public function isCustomMessageAllowed(): bool
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->isCustomMessageAllowedByDefault($storeId);
        }
        return $this->giftOptionsResolver->isCustomMessageAllowed($p, $storeId);
    }

    /**
     * Get gift delivery type.
     *
     * @return string
     */
    public function getGiftDeliveryType(): string
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->getGiftDeliveryType($storeId);
        }
        return $this->giftOptionsResolver->getDeliveryType($p, $storeId);
    }

    /**
     * Check whether delivery choice is shown on storefront.
     *
     * @return bool
     */
    public function isDeliveryChoiceOnStorefront(): bool
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->isDeliveryChoiceOnStorefront($storeId);
        }
        return $this->giftOptionsResolver->isDeliveryChoiceOnStorefront($p, $storeId);
    }

    /**
     * Check whether physical delivery is offered.
     *
     * @return bool
     */
    public function isPhysicalDeliveryOffered(): bool
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            $t = $this->config->getGiftDeliveryType($storeId);
            return in_array($t, [Config::GIFT_DELIVERY_PHYSICAL, Config::GIFT_DELIVERY_BOTH], true);
        }
        return $this->giftOptionsResolver->isPhysicalDeliveryOffered($p, $storeId);
    }

    /**
     * Get gift card amount presets.
     *
     * @return float[]
     */
    public function getAmountPresets(): array
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->getAmountPresets($storeId);
        }
        return $this->giftOptionsResolver->getAmountPresets($p, $storeId);
    }

    /**
     * Get minimum gift card amount.
     *
     * @return float
     */
    public function getMinAmount(): float
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->getMinAmount($storeId);
        }
        return $this->giftOptionsResolver->getMinAmount($p, $storeId);
    }

    /**
     * Get maximum gift card amount.
     *
     * @return float
     */
    public function getMaxAmount(): float
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->getMaxAmount($storeId);
        }
        return $this->giftOptionsResolver->getMaxAmount($p, $storeId);
    }

    /**
     * Get current currency symbol for storefront.
     *
     * @return string
     */
    public function getCurrencySymbol(): string
    {
        return (string) $this->priceCurrency->getCurrencySymbol(
            null,
            $this->_storeManager->getStore()->getCurrentCurrencyCode()
        );
    }

    /**
     * Format an amount for storefront display.
     *
     * @param float $amount Amount
     *
     * @return string
     */
    public function formatAmount(float $amount): string
    {
        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $this->_storeManager->getStore()
        );
    }
}
