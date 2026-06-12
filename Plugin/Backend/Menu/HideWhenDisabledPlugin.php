<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Backend\Menu;

use Magento\Backend\Model\Menu;
use Magento\Backend\Model\Menu\Builder;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;

/**
 * Removes Gift Card admin menu entries when the module is disabled at default scope.
 */
class HideWhenDisabledPlugin
{
    /**
     * Menu item IDs registered by Venbhas_GiftCard.
     */
    private const GIFT_CARD_MENU_IDS = [
        'Venbhas_GiftCard::root',
        'Venbhas_GiftCard::codes',
        'Venbhas_GiftCard::transactions',
    ];

    /**
     * @var ModuleEnabledGuard
     */
    private ModuleEnabledGuard $moduleEnabledGuard;

    /**
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     */
    public function __construct(ModuleEnabledGuard $moduleEnabledGuard)
    {
        $this->moduleEnabledGuard = $moduleEnabledGuard;
    }

    /**
     * Strip gift card menu items when the extension is disabled globally.
     *
     * @param Builder $subject Menu builder
     * @param Menu $menu Built menu
     *
     * @return Menu
     */
    public function afterGetResult(Builder $subject, Menu $menu): Menu
    {
        if ($this->moduleEnabledGuard->isEnabledForAdmin()) {
            return $menu;
        }

        foreach (self::GIFT_CARD_MENU_IDS as $menuId) {
            $menu->remove($menuId);
        }

        return $menu;
    }
}
