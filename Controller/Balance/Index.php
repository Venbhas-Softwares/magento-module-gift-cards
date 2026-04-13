<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Balance;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as CodeCollectionFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CodeCollectionFactory $codeCollectionFactory
    ) {}

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
            'balance' => (float)$giftCard->getData('balance'),
            'currency' => (string)$giftCard->getData('currency_code'),
            'expires_at' => $giftCard->getData('expires_at'),
        ]);
    }
}

