<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Checkout\LayoutProcessor;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\Config;

/**
 * Adds gift card checkout UI components when the module is enabled for the current store.
 */
class Checkout implements LayoutProcessorInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @param Config $config Module config
     * @param StoreManagerInterface $storeManager Store manager
     * @param CustomerSession $customerSession Customer session
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
    }

    /**
     * @inheritdoc
     */
    public function process($jsLayout)
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        if (!$this->config->isEnabled($storeId) || !$this->customerSession->isLoggedIn()) {
            return $jsLayout;
        }

        $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']
        ['children']['afterMethods']['children']['venbhas_giftcard_form'] = [
            'component' => 'Venbhas_GiftCard/js/view/payment/giftcard',
            'children' => [
                'messages' => [
                    'component' => 'Magento_SalesRule/js/view/payment/discount-messages',
                    'displayArea' => 'messages',
                ],
            ],
        ];

        $jsLayout['components']['checkout']['children']['sidebar']['children']['summary']['children']['totals']
        ['children']['before_grandtotal']['children']['venbhas_giftcard'] = [
            'component' => 'Venbhas_GiftCard/js/view/checkout/summary/totals',
            'config' => [
                'title' => $this->config->getTotalTitle(),
            ],
        ];

        return $jsLayout;
    }
}
