<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Catalog\Product;

use Magento\Catalog\Model\Product\Type;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Removes the gift card product type from type selectors when the module is disabled.
 */
class TypePlugin
{
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
     * Remove gift card from product type label map when the module is disabled.
     *
     * @param Type $subject Product type model
     * @param array $result Type id to label map
     *
     * @return array
     */
    public function afterGetOptionArray(Type $subject, array $result): array
    {
        return $this->removeGiftCardType($result);
    }

    /**
     * Remove gift card from registered product type configuration when the module is disabled.
     *
     * @param Type $subject Product type model
     * @param array $result Product type configuration
     *
     * @return array
     */
    public function afterGetTypes(Type $subject, array $result): array
    {
        return $this->removeGiftCardType($result);
    }

    /**
     * @param array $types Product types keyed by type id
     *
     * @return array
     */
    private function removeGiftCardType(array $types): array
    {
        if ($this->isGiftCardTypeVisible()) {
            return $types;
        }

        unset($types[GiftCard::TYPE_CODE]);

        return $types;
    }

    /**
     * Whether the gift card product type should appear in selectors for the current area.
     *
     * @return bool
     */
    private function isGiftCardTypeVisible(): bool
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
            return $this->moduleEnabledGuard->isEnabled((int) $this->storeManager->getStore()->getId());
        }

        return $this->moduleEnabledGuard->isEnabledForAdmin();
    }
}
