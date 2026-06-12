<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for gift card codes.
 */
class GiftCardCode extends AbstractDb
{
    /**
     * Initialize main table and primary key field.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('venbhas_giftcard_code', 'entity_id');
    }
}
