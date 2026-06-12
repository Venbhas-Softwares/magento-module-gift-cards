<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Venbhas\GiftCard\Model\GiftCardCode;

class GiftCardStatus implements OptionSourceInterface
{
    /**
     * Return yes/no options for redeemed flag rendering.
     *
     * @return array<int, array<string, int|string|\Magento\Framework\Phrase>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => GiftCardCode::STATUS_ACTIVE, 'label' => __('Yes')],
            ['value' => GiftCardCode::STATUS_INACTIVE, 'label' => __('No')],
        ];
    }
}
