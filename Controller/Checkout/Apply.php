<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Venbhas\GiftCard\Model\CustomerGiftCardTransactionsLoader;

/**
 * Checkout controller to apply gift amount (wallet) via AJAX.
 */
class Apply implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

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
    private $walletLoader;

    /**
     * Initialize controller.
     *
     * @param RequestInterface $request Request
     * @param JsonFactory $jsonFactory JSON result factory
     * @param CheckoutSession $checkoutSession Checkout session
     * @param CustomerGiftCardTransactionsLoader $walletLoader Wallet balance loader
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        CustomerGiftCardTransactionsLoader $walletLoader
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->walletLoader = $walletLoader;
    }

    /**
     * Execute action.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            $amount = (float) $this->request->getParam('amount');
            if ($amount <= 0.0001) {
                $payload = $this->readJsonBody();
                $amount = (float) ($payload['amount'] ?? 0);
            }
            if ($amount <= 0.0001) {
                throw new LocalizedException(__('Please enter a gift amount.'));
            }

            $customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : 0;
            $email = strtolower(trim((string) $quote->getCustomerEmail()));
            $email = $email !== '' ? $email : null;

            $available = $this->walletLoader->getWalletBalance($customerId, $email);
            if ($amount > $available + 0.009) {
                throw new LocalizedException(
                    __('The amount cannot exceed your available gift balance (%1).', number_format($available, 2))
                );
            }

            $quote->setData('venbhas_giftcard_amount', $amount);
            $quote->setData('base_venbhas_giftcard_amount', $amount);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $quote->save();

            return $result->setData(['success' => true, 'message' => (string)__('Gift amount applied.')]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Read request JSON body as array.
     *
     * @return array<string,mixed>
     */
    private function readJsonBody(): array
    {
        try {
            $raw = (string)$this->request->getContent();
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
