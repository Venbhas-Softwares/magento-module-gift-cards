<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Adminhtml\Code;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Venbhas_GiftCard::codes';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Venbhas_GiftCard::codes');
        $page->getConfig()->getTitle()->prepend(__('Gift Card Codes'));
        return $page;
    }
}

