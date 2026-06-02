<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Quote\GiftCardManager;

/**
 * Cart block to display applied gift card codes.
 */
class GiftCard extends Template
{
    /**
     * @var CheckoutSession
     */
    private CheckoutSession $_checkoutSession;

    /**
     * @var GiftCardManager
     */
    private GiftCardManager $_giftCardManager;

    /**
     * @var Config
     */
    private Config $_config;

    /**
     * Initialize block.
     *
     * @param Context $context Block context
     * @param CheckoutSession $checkoutSession Checkout session
     * @param GiftCardManager $giftCardManager Gift card manager
     * @param Config $config Module config
     * @param array $data Additional data
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        GiftCardManager $giftCardManager,
        Config $config,
        array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_giftCardManager = $giftCardManager;
        $this->_config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Whether the gift card cart UI should render.
     *
     * @return bool
     */
    public function isModuleEnabled(): bool
    {
        return $this->_config->isEnabled((int) $this->_storeManager->getStore()->getId());
    }

    /**
     * Get all applied gift card codes for the current quote.
     *
     * @return string[]
     */
    public function getCodes(): array
    {
        return $this->_giftCardManager->getCodes($this->_checkoutSession->getQuote());
    }
}
