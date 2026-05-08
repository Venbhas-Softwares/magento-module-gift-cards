<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

/**
 * Ajax endpoint for the customer edit "Gift Card Transactions" classic grid.
 */
class GiftcardTransactionsGrid extends Action
{
    public const ADMIN_RESOURCE = 'Venbhas_GiftCard::transactions';

    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Raw $resultRaw */
        $resultRaw = $this->resultFactory->create(ResultFactory::TYPE_RAW);

        $grid = $this->_view->getLayout()->createBlock(
            \Venbhas\GiftCard\Block\Adminhtml\Customer\Edit\Tab\GiftCardTransactionsGrid::class,
            'venbhas_giftcard_customer_transactions_grid_ajax'
        );

        return $resultRaw->setContents($grid->toHtml());
    }
}

