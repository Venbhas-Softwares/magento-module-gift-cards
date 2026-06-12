<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Backend\App;

use Magento\Backend\App\AbstractAction;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;

/**
 * Blocks admin venbhas_giftcard/* routes when the module is disabled at default scope.
 */
class DispatchGuardPlugin
{
    private const GIFT_CARD_MODULE = 'venbhas_giftcard';

    /**
     * @var ModuleEnabledGuard
     */
    private ModuleEnabledGuard $moduleEnabledGuard;

    /**
     * @var MessageManagerInterface
     */
    private MessageManagerInterface $messageManager;

    /**
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param MessageManagerInterface $messageManager Admin flash messages
     */
    public function __construct(
        ModuleEnabledGuard $moduleEnabledGuard,
        MessageManagerInterface $messageManager
    ) {
        $this->moduleEnabledGuard = $moduleEnabledGuard;
        $this->messageManager = $messageManager;
    }

    /**
     * Redirect to dashboard when gift card admin actions are requested while disabled.
     *
     * @param AbstractAction $subject Backend action
     * @param callable $proceed Original dispatch
     * @param RequestInterface $request HTTP request
     *
     * @return ResponseInterface|ResultInterface
     */
    public function aroundDispatch(AbstractAction $subject, callable $proceed, RequestInterface $request)
    {
        if ($request->getModuleName() === self::GIFT_CARD_MODULE
            && !$this->moduleEnabledGuard->isEnabledForAdmin()
        ) {
            $this->messageManager->addErrorMessage(
                __(
                    'The Gift Card module is disabled. '
                    . 'Enable it under Stores → Configuration → Venbhas → Gift Card.'
                )
            );

            /** @var Redirect $redirect */
            $redirect = $subject->getResultRedirectFactory()->create();
            $redirect->setPath('adminhtml/dashboard/index');

            return $redirect;
        }

        return $proceed($request);
    }
}
