<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Account;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\View\Result\PageFactory;

/**
 * Customer account controller for gift cards page.
 */
class Index extends AbstractAccount
{
    /**
     * @var PageFactory
     */
    private PageFactory $_pageFactory;

    /**
     * Initialize controller.
     *
     * @param \Magento\Framework\App\Action\Context $context Context
     * @param PageFactory $pageFactory Result page factory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        PageFactory $pageFactory
    ) {
        $this->_pageFactory = $pageFactory;
        parent::__construct($context);
    }

    /**
     * Execute action.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $page = $this->_pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Gift Cards'));
        return $page;
    }
}
