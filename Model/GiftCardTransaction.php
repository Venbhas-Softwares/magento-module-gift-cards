<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\Model\AbstractModel;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction as GiftCardTransactionResource;

class GiftCardTransaction extends AbstractModel
{
    public const ACTION_REDEEM = 'redeem';

    protected function _construct(): void
    {
        $this->_init(GiftCardTransactionResource::class);
    }
}
