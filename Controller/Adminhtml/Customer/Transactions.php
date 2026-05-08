<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

class Transactions extends Action
{
    public const ADMIN_RESOURCE = 'Venbhas_GiftCard::transactions';

    public function execute()
    {
        /** @var \Magento\Framework\View\Result\Layout $resultLayout */
        $resultLayout = $this->resultFactory->create(ResultFactory::TYPE_LAYOUT);
        return $resultLayout;
    }
}

