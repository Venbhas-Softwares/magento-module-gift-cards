<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class GiftCardCode extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('venbhas_giftcard_code', 'entity_id');
    }
}

