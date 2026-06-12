<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin;

use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;

/**
 * Base plugin: no-op when the module is disabled in store configuration.
 */
abstract class AbstractPlugin
{
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
     * Whether the gift card module is enabled for the given store.
     *
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    protected function isModuleEnabled(?int $storeId = null): bool
    {
        return $this->moduleEnabledGuard->isEnabled($storeId);
    }
}
