<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Config\Source\Product;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Venbhas\GiftCard\Model\Config;

/**
 * Product attribute: empty = inherit Stores → Configuration; otherwise override.
 * Must extend AbstractSource so EAV can call
 * {@see \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource::setAttribute()}.
 */
class GiftDeliveryTypeWithConfig extends AbstractSource
{
    /**
     * Get all available options.
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function getAllOptions(): array
    {
        return [
            ['value' => '', 'label' => __('Use configuration defaults')],
            [
                'value' => Config::GIFT_DELIVERY_VIRTUAL,
                'label' => __('Virtual (email delivery only)'),
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
