<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Adminhtml\Code;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Venbhas\GiftCard\Model\GiftCardCode;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode as GiftCardCodeResource;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as GiftCardCodeCollectionFactory;

class MassStatus extends Action
{
    public const ADMIN_RESOURCE = 'Venbhas_GiftCard::codes';

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var GiftCardCodeCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var GiftCardCodeResource
     */
    private $resource;

    /**
     * @param Action\Context $context
     * @param Filter $filter
     * @param GiftCardCodeCollectionFactory $collectionFactory
     * @param GiftCardCodeResource $resource
     */
    public function __construct(
        Action\Context $context,
        Filter $filter,
        GiftCardCodeCollectionFactory $collectionFactory,
        GiftCardCodeResource $resource
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->resource = $resource;
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $status = (int)$this->getRequest()->getParam('status');
        if (!in_array($status, [GiftCardCode::STATUS_ACTIVE, GiftCardCode::STATUS_INACTIVE], true)) {
            $this->messageManager->addErrorMessage(__('Invalid status.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $collection = $this->filter->getCollection($this->collectionFactory->create());

        $updated = 0;
        $skippedPending = 0;

        /** @var GiftCardCode $item */
        foreach ($collection as $item) {
            $current = (int)$item->getData('status');
            if ($current === GiftCardCode::STATUS_PENDING) {
                $skippedPending++;
                continue;
            }
            if ($current === $status) {
                continue;
            }
            $item->setData('status', $status);
            $this->resource->save($item);
            $updated++;
        }

        if ($updated > 0) {
            $this->messageManager->addSuccessMessage(__('Updated %1 gift card code(s).', $updated));
        }
        if ($skippedPending > 0) {
            $this->messageManager->addNoticeMessage(
                __('Skipped %1 pending gift card code(s) (cannot be manually activated/deactivated).', $skippedPending)
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}

