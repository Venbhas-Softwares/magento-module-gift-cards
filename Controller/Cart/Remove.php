<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Venbhas\GiftCard\Model\Quote\GiftCardManager;

/**
 * Controller action to remove an applied gift card code from cart.
 */
class Remove implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $_request;

    /**
     * @var RedirectFactory
     */
    private RedirectFactory $_redirectFactory;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $_messageManager;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $_checkoutSession;

    /**
     * @var GiftCardManager
     */
    private GiftCardManager $_giftCardManager;

    /**
     * Initialize controller.
     *
     * @param RequestInterface $request Request
     * @param RedirectFactory $redirectFactory Redirect factory
     * @param ManagerInterface $messageManager Message manager
     * @param CheckoutSession $checkoutSession Checkout session
     * @param GiftCardManager $giftCardManager Gift card manager
     */
    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        GiftCardManager $giftCardManager
    ) {
        $this->_request = $request;
        $this->_redirectFactory = $redirectFactory;
        $this->_messageManager = $messageManager;
        $this->_checkoutSession = $checkoutSession;
        $this->_giftCardManager = $giftCardManager;
    }

    /**
     * Execute action.
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $result = $this->_redirectFactory->create();
        $result->setPath('checkout/cart');

        $code = (string)$this->_request->getParam('giftcard_code');
        $code = strtoupper(trim($code));
        if ($code === '') {
            $this->_messageManager->addErrorMessage(__('Missing gift card code.'));
            return $result;
        }

        $quote = $this->_checkoutSession->getQuote();
        $this->_giftCardManager->removeCode($quote, $code);
        $this->_messageManager->addSuccessMessage(__('Gift card code removed.'));
        return $result;
    }
}
