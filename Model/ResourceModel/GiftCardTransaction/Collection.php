<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction as GiftCardTransactionResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(GiftCardTransaction::class, GiftCardTransactionResource::class);
    }

    /**
     * @return $this
     */
    public function joinGiftCardCode(): self
    {
        $gcTable = $this->getTable('venbhas_giftcard_code');
        $this->getSelect()->join(
            ['gc' => $gcTable],
            'main_table.giftcard_id = gc.entity_id',
            ['giftcard_code' => 'gc.code', 'gc_currency' => 'gc.currency_code']
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function joinSalesOrder(): self
    {
        $soTable = $this->getTable('sales_order');
        $this->getSelect()->joinLeft(
            ['so' => $soTable],
            'main_table.order_id = so.entity_id',
            ['increment_id' => 'so.increment_id']
        );

        return $this;
    }
}
