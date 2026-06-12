<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Frontend\App;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;

/**
 * Blocks storefront venbhas_giftcard/* routes when the module is disabled for the current store.
 */
class DispatchGuardPlugin
{
    private const GIFT_CARD_MODULE = 'venbhas_giftcard';

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
     * Forward to noroute when gift card storefront actions are requested while disabled.
     *
     * @param Action $subject Storefront action
     * @param callable $proceed Original dispatch
     * @param RequestInterface $request HTTP request
     *
     * @return ResponseInterface
     */
    public function aroundDispatch(Action $subject, callable $proceed, RequestInterface $request)
    {
        if ($request->getModuleName() !== self::GIFT_CARD_MODULE) {
            return $proceed($request);
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        if ($this->moduleEnabledGuard->isEnabled($storeId)) {
            return $proceed($request);
        }

        $request->initForward();
        $request->setModuleName('cms');
        $request->setControllerName('noroute');
        $request->setActionName('index');
        $request->setDispatched(false);

        return $proceed($request);
    }
}
