<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Checkout\LayoutProcessor;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\Config;

/**
 * Adds gift card cart totals UI when the module is enabled for the current store.
 */
class Cart implements LayoutProcessorInterface
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

        $jsLayout['components']['block-totals']['children']['venbhas_giftcard'] = [
            'component' => 'Venbhas_GiftCard/js/view/checkout/cart/totals',
            'sortOrder' => '65',
            'config' => [
                'template' => 'Venbhas_GiftCard/checkout/cart/giftcard',
                'title' => $this->config->getTotalTitle(),
            ],
        ];

        return $jsLayout;
    }
}
