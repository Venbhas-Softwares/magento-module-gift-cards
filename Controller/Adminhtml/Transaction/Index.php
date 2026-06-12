<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin controller action for gift card transactions grid.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'Venbhas_GiftCard::transactions';

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * Initialize controller.
     *
     * @param Action\Context $context Context
     * @param PageFactory $pageFactory Result page factory
     */
    public function __construct(
        Action\Context $context,
        PageFactory $pageFactory
    ) {
        $this->pageFactory = $pageFactory;
        parent::__construct($context);
    }

    /**
     * Execute action.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Venbhas_GiftCard::transactions');
        $page->getConfig()->getTitle()->prepend(__('Gift Card Transactions'));
        return $page;
    }
}
