<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as CodeCollectionFactory;

class MyGiftCards extends Template
{
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CodeCollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getGiftCards()
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setOrder('created_at', 'DESC');
        return $collection;
    }
}

