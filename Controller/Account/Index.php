<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Account;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractAccount
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Gift Cards'));
        return $page;
    }
}

