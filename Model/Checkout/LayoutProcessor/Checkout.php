<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Checkout\LayoutProcessor;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
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
     * @param Config $config Module config
     * @param StoreManagerInterface $storeManager Store manager
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function process($jsLayout)
    {
        if (!$this->config->isEnabled((int) $this->storeManager->getStore()->getId())) {
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
