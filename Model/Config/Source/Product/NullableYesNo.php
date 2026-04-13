<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Config\Source\Product;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * Product-level tri-state: inherit store config, force yes, or force no.
 * Must extend AbstractSource for EAV attribute source models.
 */
class NullableYesNo extends AbstractSource
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function getAllOptions(): array
    {
        return [
            ['value' => '', 'label' => __('Use configuration defaults')],
            ['value' => '1', 'label' => __('Yes')],
            ['value' => '0', 'label' => __('No')],
        ];
    }
}
