<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Order\Email;

use Magento\Framework\View\LayoutInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Adminhtml\Order\Totals as AdminOrderTotals;
use Magento\Sales\Block\Order\Totals;

/**
 * Renders order totals HTML for transactional emails that do not include a totals child block.
 */
class OrderTotalsRenderer
{
    /**
     * @var int
     */
    private $blockSequence = 0;

    /**
     * Build a totals table fragment for shipment emails and print views.
     *
     * @param LayoutInterface $layout
     * @param OrderInterface $order
     * @param bool $wrapForEmail When true, wrap markup like core order confirmation emails.
     * @return string
     */
    public function render(LayoutInterface $layout, OrderInterface $order, bool $wrapForEmail = true): string
    {
        $totalsBlock = $this->createOrderTotalsBlock($layout, $order);
        if ($wrapForEmail) {
            $totalsBlock->setLabelProperties('colspan="2"');
        }

        $html = trim($totalsBlock->toHtml());
        if ($html === '') {
            return '';
        }

        if (!$wrapForEmail) {
            return $html;
        }

        return '<table class="email-items"><tfoot class="order-totals">' . $html . '</tfoot></table>';
    }

    /**
     * Render totals for print views (valid table wrapper; totals.phtml outputs &lt;tr&gt; rows only).
     *
     * @param LayoutInterface $layout
     * @param OrderInterface $order
     * @return string
     */
    public function renderForPrintView(LayoutInterface $layout, OrderInterface $order): string
    {
        $totalsBlock = $this->createOrderTotalsBlock($layout, $order);
        $totalsBlock->setLabelProperties('colspan="3" class="mark"');
        $totalsBlock->setValueProperties('class="amount"');

        $rows = trim($totalsBlock->toHtml());
        if ($rows === '') {
            return '';
        }

        return '<div class="table-wrapper order-totals-wrapper">'
            . '<table class="data table table-order-totals"><tbody>'
            . $rows
            . '</tbody></table></div>';
    }

    /**
     * Render admin-styled order totals (e.g. on shipment view in admin).
     *
     * @param LayoutInterface $layout
     * @param OrderInterface $order
     * @return string
     */
    public function renderAdmin(LayoutInterface $layout, OrderInterface $order): string
    {
        /** @var AdminOrderTotals $totalsBlock */
        $totalsBlock = $layout->createBlock(
            AdminOrderTotals::class,
            $this->nextBlockName('venbhas.giftcard.admin.order_totals'),
            ['data' => ['order' => $order]]
        );
        $totalsBlock->setTemplate('Magento_Sales::order/totals.phtml');

        return trim($totalsBlock->toHtml());
    }

    /**
     * Create a storefront order totals block for the given order.
     *
     * @param LayoutInterface $layout
     * @param OrderInterface $order
     * @return Totals
     */
    private function createOrderTotalsBlock(LayoutInterface $layout, OrderInterface $order): Totals
    {
        /** @var Totals $totalsBlock */
        $totalsBlock = $layout->createBlock(
            Totals::class,
            $this->nextBlockName('venbhas.giftcard.order_totals'),
            ['data' => ['order' => $order]]
        );
        $totalsBlock->setTemplate('Magento_Sales::order/totals.phtml');

        return $totalsBlock;
    }

    /**
     * Generate a unique block name for layout creation.
     *
     * @param string $prefix
     * @return string
     */
    private function nextBlockName(string $prefix): string
    {
        return $prefix . '.' . (++$this->blockSequence);
    }
}
