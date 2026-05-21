<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Checkout controller to remove gift amount (wallet) via AJAX.
 */
class Remove implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @param JsonFactory $jsonFactory JSON result factory
     * @param CheckoutSession $checkoutSession Checkout session
     */
    public function __construct(
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Remove gift card amount from quote and return JSON result.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            $quote->setData('venbhas_giftcard_amount', null);
            $quote->setData('base_venbhas_giftcard_amount', null);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $quote->save();

            return $result->setData(['success' => true, 'message' => (string) __('Gift amount removed.')]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
