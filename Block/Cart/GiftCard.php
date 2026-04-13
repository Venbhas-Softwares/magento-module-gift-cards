<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Venbhas\GiftCard\Model\Quote\GiftCardManager;

class GiftCard extends Template
{
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly GiftCardManager $giftCardManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string[]
     */
    public function getCodes(): array
    {
        return $this->giftCardManager->getCodes($this->checkoutSession->getQuote());
    }
}

