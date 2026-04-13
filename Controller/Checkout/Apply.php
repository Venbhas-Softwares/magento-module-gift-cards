<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Venbhas\GiftCard\Model\Quote\GiftCardManager;

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
     * @var GiftCardManager
     */
    private $giftCardManager;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        GiftCardManager $giftCardManager
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->giftCardManager = $giftCardManager;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $code = (string)$this->request->getParam('giftcard_code');
            if (trim($code) === '') {
                $payload = $this->readJsonBody();
                $code = (string)($payload['giftcard_code'] ?? '');
            }
            $code = strtoupper(trim($code));
            if ($code === '') {
                throw new LocalizedException(__('Please enter a gift card code.'));
            }
            $quote = $this->checkoutSession->getQuote();
            $this->giftCardManager->addCode($quote, $code);
            return $result->setData(['success' => true, 'message' => (string)__('Gift card code applied.')]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
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

