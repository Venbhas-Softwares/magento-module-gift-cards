<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for gift card transactions ledger.
 */
class GiftCardTransaction extends AbstractDb
{
    /**
     * Initialize main table and primary key field.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('venbhas_giftcard_transaction', 'entity_id');
    }
}
