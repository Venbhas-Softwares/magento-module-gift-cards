<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\CollectionFactory;

class GiftCardTransactions extends Template
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
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
        $cid = (int) $this->customerSession->getCustomerId();
        if ($cid <= 0) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->joinGiftCardCode();
        $collection->joinSalesOrder();
        $collection->addFieldToFilter('main_table.customer_id', $cid);
        $collection->addFieldToFilter('main_table.action', GiftCardTransaction::ACTION_REDEEM);
        $collection->setOrder('main_table.entity_id', 'desc');
        $collection->setPageSize(100);

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

    public function getOrderViewUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }
}
