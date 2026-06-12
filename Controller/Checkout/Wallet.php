<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Venbhas\GiftCard\Model\CustomerGiftCardTransactionsLoader;

/**
 * Returns gift wallet balance for the current checkout customer.
 */
class Wallet implements HttpGetActionInterface
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
     * @var CustomerGiftCardTransactionsLoader
     */
    private $transactionsLoader;

    /**
     * @param JsonFactory $jsonFactory JSON result factory
     * @param CheckoutSession $checkoutSession Checkout session
     * @param CustomerGiftCardTransactionsLoader $transactionsLoader Wallet balance loader
     */
    public function __construct(
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        CustomerGiftCardTransactionsLoader $transactionsLoader
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->transactionsLoader = $transactionsLoader;
    }

    /**
     * Return the current wallet balance for the checkout quote customer.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return $result->setData(['success' => true, 'balance' => 0.0]);
            }

            $customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : 0;
            $email = strtolower(trim((string) $quote->getCustomerEmail()));
            $email = $email !== '' ? $email : null;

            $balance = $this->transactionsLoader->getWalletBalance($customerId, $email);

            return $result->setData(['success' => true, 'balance' => (float) $balance]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage(), 'balance' => 0.0]);
        }
    }
}
