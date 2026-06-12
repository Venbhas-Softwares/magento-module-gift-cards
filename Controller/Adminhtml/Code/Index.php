<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Adminhtml\Code;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin controller action for gift card codes index page.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'Venbhas_GiftCard::codes';

    /**
     * @var PageFactory
     */
    private PageFactory $_pageFactory;

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
        $page->setActiveMenu('Venbhas_GiftCard::codes');
        $page->getConfig()->getTitle()->prepend(__('Gift Card Codes'));
        return $page;
    }
}
