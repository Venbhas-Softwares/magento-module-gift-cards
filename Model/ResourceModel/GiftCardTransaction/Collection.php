<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction as GiftCardTransactionResource;

/**
 * Gift card transaction collection.
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize collection.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(GiftCardTransaction::class, GiftCardTransactionResource::class);
    }

    /**
     * Join gift card code information (code and currency).
     *
     * @return $this
     */
    public function joinGiftCardCode(): self
    {
        $gcTable = $this->getTable('venbhas_giftcard_code');
        $this->getSelect()->joinLeft(
            ['gc' => $gcTable],
            'main_table.giftcard_id = gc.entity_id',
            ['giftcard_code' => 'gc.code']
        );

        return $this;
    }

    /**
     * Join sales order increment id.
     *
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
