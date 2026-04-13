<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Adminhtml\Customer\Edit\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\CollectionFactory;

class GiftCardTransactions extends Template implements TabInterface
{
    protected $_template = 'Venbhas_GiftCard::customer/giftcard_transactions.phtml';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CollectionFactory $collectionFactory,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return GiftCardTransaction[]
     */
    public function getTransactions(): array
    {
        $cid = (int) $this->registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
        if ($cid <= 0) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->joinGiftCardCode();
        $collection->joinSalesOrder();
        $collection->addFieldToFilter('main_table.customer_id', $cid);
        $collection->addFieldToFilter('main_table.action', GiftCardTransaction::ACTION_REDEEM);
        $collection->setOrder('main_table.entity_id', 'desc');
        $collection->setPageSize(200);

        return $collection->getItems();
    }

    public function formatAmount(float $amount, ?string $currencyCode): string
    {
        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            null,
            $currencyCode
        );
    }

    public function getTabLabel(): \Magento\Framework\Phrase
    {
        return __('Gift Card Transactions');
    }

    public function getTabTitle(): \Magento\Framework\Phrase
    {
        return __('Gift Card Transactions');
    }

    public function canShowTab(): bool
    {
        return (int) $this->registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID) > 0;
    }

    public function isHidden(): bool
    {
        return !$this->canShowTab();
    }

    public function getTabUrl(): string
    {
        return '';
    }

    public function getTabClass(): string
    {
        return '';
    }

    public function isAjaxLoaded(): bool
    {
        return false;
    }

    public function getAdminOrderUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }
}
