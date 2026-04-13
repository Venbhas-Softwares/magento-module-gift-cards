<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Venbhas\GiftCard\Model\Config;

class GiftDeliveryType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::GIFT_DELIVERY_VIRTUAL,
                'label' => __('Virtual (email delivery)'),
            ],
            [
                'value' => Config::GIFT_DELIVERY_PHYSICAL,
                'label' => __('Physical (shipped / printed card)'),
            ],
            [
                'value' => Config::GIFT_DELIVERY_BOTH,
                'label' => __('Both (customer chooses on product page)'),
            ],
        ];
    }
}
