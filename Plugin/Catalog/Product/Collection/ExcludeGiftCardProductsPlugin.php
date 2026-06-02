<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Catalog\Product\Collection;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Excludes gift card products from product collections when the module is disabled.
 */
class ExcludeGiftCardProductsPlugin
{
    private const FILTER_FLAG = 'venbhas_giftcard_type_excluded';

    /**
     * @var ModuleEnabledGuard
     */
    private ModuleEnabledGuard $moduleEnabledGuard;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param State $appState Application area state
     * @param StoreManagerInterface $storeManager Store manager
     */
    public function __construct(
        ModuleEnabledGuard $moduleEnabledGuard,
        State $appState,
        StoreManagerInterface $storeManager
    ) {
        $this->moduleEnabledGuard = $moduleEnabledGuard;
        $this->appState = $appState;
        $this->storeManager = $storeManager;
    }

    /**
     * Add type filter before collection load when the module is disabled for the current area.
     *
     * @param Collection $subject Product collection
     *
     * @return void
     */
    public function beforeLoad(Collection $subject): void
    {
        if ($subject->getFlag(self::FILTER_FLAG)) {
            return;
        }

        if ($this->shouldIncludeGiftCardProducts($subject)) {
            return;
        }

        $subject->addFieldToFilter('type_id', ['neq' => GiftCard::TYPE_CODE]);
        $subject->setFlag(self::FILTER_FLAG, true);
    }

    /**
     * Whether gift card products should remain in the collection.
     *
     * @param Collection $subject Product collection
     *
     * @return bool
     */
    private function shouldIncludeGiftCardProducts(Collection $subject): bool
    {
        try {
            $areaCode = $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $this->moduleEnabledGuard->isEnabledForAdmin();
        }

        if ($areaCode === Area::AREA_ADMINHTML) {
            return $this->moduleEnabledGuard->isEnabledForAdmin();
        }

        if ($areaCode === Area::AREA_FRONTEND) {
            $storeId = (int) ($subject->getStoreId() ?: $this->storeManager->getStore()->getId());

            return $this->moduleEnabledGuard->isEnabled($storeId);
        }

        return true;
    }
}
