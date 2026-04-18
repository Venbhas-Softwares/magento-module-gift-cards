<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\Model\AbstractModel;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction as GiftCardTransactionResource;

class GiftCardTransaction extends AbstractModel
{
    public const ACTION_REDEEM = 'redeem';
    /** Optional ledger row when checkout_apply logger is enabled (balance uses ACTION_REDEEM redemption). */
    public const ACTION_CHECKOUT_APPLY = 'checkout_apply';

    protected function _construct(): void
    {
        $this->_init(GiftCardTransactionResource::class);
    }
}
