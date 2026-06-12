<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Sales\Order;

use Magento\Sales\Block\Order\PrintOrder\Shipment;
use Magento\Sales\Block\Order\Totals;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Order\Email\OrderTotalsRenderer;
use Venbhas\GiftCard\Model\Order\GiftCardTotalsApplier;

/**
 * Print-shipment: append order totals (with gift card row) after shipment items, in a proper table.
 */
class PrintShipmentPlugin
{
    private const MAIN_CONTENT_BLOCK = 'sales.order.print.shipment';

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
     * @param OrderTotalsRenderer $totalsRenderer Totals HTML renderer
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
     * Append order totals with gift card row after shipment items on print view.
     *
     * @param Shipment $subject Print shipment block
     * @param string $result Rendered HTML
     * @return string
     */
    public function afterToHtml(Shipment $subject, string $result): string
    {
        // PrintOrder\Shipment is also used for order.status and order.date in the page title.
        if ($subject->getNameInLayout() !== self::MAIN_CONTENT_BLOCK) {
            return $result;
        }

        if (!$this->config->isEnabled()) {
            return $result;
        }

        $order = $subject->getOrder();
        if ($order === null || !$this->applier->orderHasGiftCard($order)) {
            return $result;
        }

        $rows = $this->renderTotalsRows($subject, $order);
        if ($rows === '') {
            return $result;
        }

        return $result
            . '<div class="table-wrapper order-totals-wrapper">'
            . '<table class="data table table-order-totals"><tbody>'
            . $rows
            . '</tbody></table></div>';
    }

    /**
     * Render order totals rows for the print shipment page.
     *
     * @param Shipment $subject
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    private function renderTotalsRows(Shipment $subject, $order): string
    {
        $totalsBlock = $subject->getChildBlock('order_totals');
        if ($totalsBlock instanceof Totals) {
            $totalsBlock->setOrder($order);

            return trim((string) $totalsBlock->toHtml());
        }

        return trim($this->totalsRenderer->renderForPrintView($subject->getLayout(), $order));
    }
}
