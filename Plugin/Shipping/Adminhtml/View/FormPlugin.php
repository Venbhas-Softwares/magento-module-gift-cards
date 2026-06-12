<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Shipping\Adminhtml\View;

use Magento\Shipping\Block\Adminhtml\View\Form;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Order\Email\OrderTotalsRenderer;
use Venbhas\GiftCard\Model\Order\GiftCardTotalsApplier;

/**
 * Admin shipment view has no totals section; show order gift card amount from the related order.
 */
class FormPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var GiftCardTotalsApplier
     */
    private $applier;

    /**
     * @var OrderTotalsRenderer
     */
    private $totalsRenderer;

    /**
     * @param Config $config Module config
     * @param GiftCardTotalsApplier $applier Totals applier
     * @param OrderTotalsRenderer $totalsRenderer Totals renderer
     */
    public function __construct(
        Config $config,
        GiftCardTotalsApplier $applier,
        OrderTotalsRenderer $totalsRenderer
    ) {
        $this->config = $config;
        $this->applier = $applier;
        $this->totalsRenderer = $totalsRenderer;
    }

    /**
     * Append order totals with gift card row to admin shipment view.
     *
     * @param Form $subject Admin shipment form block
     * @param string $result Rendered HTML
     * @return string
     */
    public function afterToHtml(Form $subject, string $result): string
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        try {
            $order = $subject->getOrder();
        } catch (\Exception $e) {
            return $result;
        }

        if (!$this->applier->orderHasGiftCard($order)) {
            return $result;
        }

        $totalsHtml = $this->totalsRenderer->renderAdmin($subject->getLayout(), $order);
        if ($totalsHtml === '') {
            return $result;
        }

        return $result . '<div class="admin__page-section order-totals">'
            . '<div class="admin__page-section-title"><span class="title">'
            . $subject->escapeHtml((string) __('Order Totals'))
            . '</span></div><div class="admin__page-section-content">'
            . $totalsHtml
            . '</div></div>';
    }
}
