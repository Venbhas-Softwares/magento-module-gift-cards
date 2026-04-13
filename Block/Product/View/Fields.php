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

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    public function shouldRender(): bool
    {
        if (!$this->config->isEnabled((int) $this->_storeManager->getStore()->getId())) {
            return false;
        }
        $p = $this->getProduct();
        return $p && (string) $p->getTypeId() === GiftCard::TYPE_CODE;
    }

    public function isCustomAmountAllowed(): bool
    {
        $p = $this->getProduct();
        if (!$p) {
            return $this->config->isCustomAmountAllowed((int) $this->_storeManager->getStore()->getId());
        }
        return $this->giftOptionsResolver->isCustomAmountAllowed($p, (int) $this->_storeManager->getStore()->getId());
    }

    public function isCustomMessageAllowed(): bool
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->isCustomMessageAllowedByDefault($storeId);
        }
        return $this->giftOptionsResolver->isCustomMessageAllowed($p, $storeId);
    }

    public function getGiftDeliveryType(): string
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->getGiftDeliveryType($storeId);
        }
        return $this->giftOptionsResolver->getDeliveryType($p, $storeId);
    }

    public function isDeliveryChoiceOnStorefront(): bool
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->isDeliveryChoiceOnStorefront($storeId);
        }
        return $this->giftOptionsResolver->isDeliveryChoiceOnStorefront($p, $storeId);
    }

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

    public function getMinAmount(): float
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->getMinAmount($storeId);
        }
        return $this->giftOptionsResolver->getMinAmount($p, $storeId);
    }

    public function getMaxAmount(): float
    {
        $p = $this->getProduct();
        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$p) {
            return $this->config->getMaxAmount($storeId);
        }
        return $this->giftOptionsResolver->getMaxAmount($p, $storeId);
    }

    public function getCurrencySymbol(): string
    {
        return (string) $this->priceCurrency->getCurrencySymbol(
            null,
            $this->_storeManager->getStore()->getCurrentCurrencyCode()
        );
    }

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
