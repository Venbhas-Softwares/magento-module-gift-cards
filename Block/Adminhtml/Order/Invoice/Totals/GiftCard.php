<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Adminhtml\Order\Invoice\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;

class GiftCard extends Template
{
    /**
     * Add Gift Card total row to invoice totals table.
     */
    public function initTotals(): self
    {
        $parent = $this->getParentBlock();
        if (!$parent || !method_exists($parent, 'getSource') || !method_exists($parent, 'addTotal')) {
            return $this;
        }

        $source = $parent->getSource();
        if (!$source) {
            return $this;
        }

        $baseAmount = (float) ($source->getData('base_venbhas_giftcard_amount') ?? 0);
        $amount = (float) ($source->getData('venbhas_giftcard_amount') ?? 0);
        if ($baseAmount <= 0.0001 && $amount <= 0.0001) {
            return $this;
        }

        $total = new DataObject([
            'code' => 'venbhas_giftcard',
            'value' => -abs($amount ?: $baseAmount),
            'base_value' => -abs($baseAmount ?: $amount),
            'label' => __('Gift Card'),
        ]);

        $after = $parent->getTotal('tax') ? 'tax' : null;
        $parent->addTotal($total, $after);

        return $this;
    }
}
