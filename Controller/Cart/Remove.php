<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Venbhas\GiftCard\Model\Quote\GiftCardManager;

class Remove implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly CheckoutSession $checkoutSession,
        private readonly GiftCardManager $giftCardManager
    ) {}

    public function execute()
    {
        $result = $this->redirectFactory->create();
        $result->setPath('checkout/cart');

        $code = (string)$this->request->getParam('giftcard_code');
        $code = strtoupper(trim($code));
        if ($code === '') {
            $this->messageManager->addErrorMessage(__('Missing gift card code.'));
            return $result;
        }

        $quote = $this->checkoutSession->getQuote();
        $this->giftCardManager->removeCode($quote, $code);
        $this->messageManager->addSuccessMessage(__('Gift card code removed.'));
        return $result;
    }
}

