<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Venbhas\GiftCard\Model\GiftCardCode;

class GiftCardStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => GiftCardCode::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => GiftCardCode::STATUS_INACTIVE, 'label' => __('Inactive')],
            ['value' => GiftCardCode::STATUS_PENDING, 'label' => __('Pending')],
        ];
    }
}

