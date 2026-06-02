<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Config;

use Venbhas\GiftCard\Model\Config;

/**
 * Central guard for store-config module enable flag (venbhas_giftcard/general/enabled).
 */
class ModuleEnabledGuard
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param Config $config Module config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Whether gift card features should run for the given store.
     *
     * @param int|null $storeId Store ID
     *
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->config->isEnabled($storeId);
    }

    /**
     * Whether admin gift card menus and controllers should be available (default config scope).
     *
     * @return bool
     */
    public function isEnabledForAdmin(): bool
    {
        return $this->config->isModuleEnabledForAdmin();
    }
}
