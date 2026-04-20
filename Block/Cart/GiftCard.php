<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
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
     * Initialize block.
     *
     * @param Context $context Block context
     * @param CheckoutSession $checkoutSession Checkout session
     * @param GiftCardManager $giftCardManager Gift card manager
     * @param array $data Additional data
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        GiftCardManager $giftCardManager,
        array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;
        $this->_giftCardManager = $giftCardManager;
        parent::__construct($context, $data);
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
