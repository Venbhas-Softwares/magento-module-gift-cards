<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Catalog\Product;

use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Prevents gift card products from being salable when the module is disabled for the current store.
 */
class SalablePlugin
{
    /**
     * @var ModuleEnabledGuard
     */
    private ModuleEnabledGuard $moduleEnabledGuard;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param StoreManagerInterface $storeManager Store manager
     */
    public function __construct(
        ModuleEnabledGuard $moduleEnabledGuard,
        StoreManagerInterface $storeManager
    ) {
        $this->moduleEnabledGuard = $moduleEnabledGuard;
        $this->storeManager = $storeManager;
    }

    /**
     * Mark gift card products as not salable when the module is disabled.
     *
     * @param Product $subject Product
     * @param bool $result Original isSalable result
     *
     * @return bool
     */
    public function afterIsSalable(Product $subject, bool $result): bool
    {
        if (!$result || (string) $subject->getTypeId() !== GiftCard::TYPE_CODE) {
            return $result;
        }

        $storeId = (int) ($subject->getStoreId() ?: $this->storeManager->getStore()->getId());
        if ($this->moduleEnabledGuard->isEnabled($storeId)) {
            return $result;
        }

        return false;
    }
}
