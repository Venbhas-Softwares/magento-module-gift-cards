<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\Model\AbstractModel;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode as ResourceModel;

class GiftCardCode extends AbstractModel
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;

    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}

