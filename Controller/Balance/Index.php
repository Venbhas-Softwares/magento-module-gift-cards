<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Balance;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as CodeCollectionFactory;

/**
 * Balance lookup controller for gift card codes.
 */
class Index implements HttpGetActionInterface
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
     * @var CodeCollectionFactory
     */
    private $codeCollectionFactory;

    /**
     * Initialize controller.
     *
     * @param RequestInterface $request Request
     * @param JsonFactory $jsonFactory JSON result factory
     * @param CodeCollectionFactory $codeCollectionFactory Gift card code collection factory
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CodeCollectionFactory $codeCollectionFactory
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->codeCollectionFactory = $codeCollectionFactory;
    }

    /**
     * Execute action.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $code = strtoupper(trim((string)$this->request->getParam('code')));
        $result = $this->jsonFactory->create();

        if ($code === '') {
            return $result->setData(['success' => false, 'message' => (string)__('Missing code.')]);
        }

        $collection = $this->codeCollectionFactory->create();
        $collection->addFieldToFilter('code', $code);
        $giftCard = $collection->getFirstItem();
        if (!$giftCard->getId()) {
            return $result->setData(['success' => false, 'message' => (string)__('Invalid gift card code.')]);
        }

        return $result->setData([
            'success' => true,
            'code' => $code,
            'status' => (int)$giftCard->getData('status'),
            // Keep API key 'balance' for backward compatibility with existing JS.
            'balance' => (float) ($giftCard->getData('balance_amount')
                ?? $giftCard->getData('amount')
                ?? $giftCard->getData('balance')
                ?? 0),
            'currency' => (string)$giftCard->getData('currency_code'),
            'expires_at' => $giftCard->getData('expires_at'),
        ]);
    }
}
