<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Product\Type;

use Magento\Catalog\Api\Data\ProductTierPriceExtensionFactory;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Price as CatalogPrice;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;

/**
 * Gift card catalog price: use first available preset (or minimum) for listing and price display.
 */
class Price extends CatalogPrice
{
    /**
     * @param RuleFactory $ruleFactory Catalog rule factory
     * @param StoreManagerInterface $storeManager Store manager
     * @param TimezoneInterface $localeDate Locale date
     * @param CustomerSession $customerSession Customer session
     * @param ManagerInterface $eventManager Event manager
     * @param PriceCurrencyInterface $priceCurrency Price currency
     * @param GroupManagementInterface $groupManagement Group management
     * @param ProductTierPriceInterfaceFactory $tierPriceFactory Tier price factory
     * @param ScopeConfigInterface $config Scope config
     * @param GiftOptionsResolver $giftOptionsResolver Gift options resolver
     * @param ProductTierPriceExtensionFactory|null $tierPriceExtensionFactory Tier price extension factory
     */
    public function __construct(
        RuleFactory $ruleFactory,
        StoreManagerInterface $storeManager,
        TimezoneInterface $localeDate,
        CustomerSession $customerSession,
        ManagerInterface $eventManager,
        PriceCurrencyInterface $priceCurrency,
        GroupManagementInterface $groupManagement,
        ProductTierPriceInterfaceFactory $tierPriceFactory,
        ScopeConfigInterface $config,
        private readonly GiftOptionsResolver $giftOptionsResolver,
        ?ProductTierPriceExtensionFactory $tierPriceExtensionFactory = null
    ) {
        parent::__construct(
            $ruleFactory,
            $storeManager,
            $localeDate,
            $customerSession,
            $eventManager,
            $priceCurrency,
            $groupManagement,
            $tierPriceFactory,
            $config,
            $tierPriceExtensionFactory
        );
    }

    /**
     * @inheritdoc
     */
    public function getPrice($product)
    {
        if ($this->isGiftCard($product)) {
            return $this->resolveListingAmount($product);
        }

        return parent::getPrice($product);
    }

    /**
     * @inheritdoc
     */
    public function getBasePrice($product, $qty = null)
    {
        if ($this->isGiftCard($product)) {
            return $this->resolveListingAmount($product);
        }

        return parent::getBasePrice($product, $qty);
    }

    /**
     * Check whether the product is a gift card type.
     *
     * @param Product $product
     * @return bool
     */
    private function isGiftCard(Product $product): bool
    {
        return (string) $product->getTypeId() === GiftCard::TYPE_CODE;
    }

    /**
     * Resolve the listing amount for a gift card product.
     *
     * @param Product $product
     * @return float
     */
    private function resolveListingAmount(Product $product): float
    {
        $storeId = (int) $product->getStoreId();

        return $this->giftOptionsResolver->getListingFromAmount(
            $product,
            $storeId > 0 ? $storeId : null
        );
    }
}
