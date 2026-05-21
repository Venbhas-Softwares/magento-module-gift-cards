<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Sales\Order\Email;

use Magento\Sales\Block\Order\Email\Shipment\Items;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Order\Email\OrderTotalsRenderer;
use Venbhas\GiftCard\Model\Order\GiftCardTotalsApplier;

/**
 * Shipment emails have no order_totals child; append order totals when a gift card was applied.
 */
class ShipmentItemsPlugin
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
     * @param OrderTotalsRenderer $totalsRenderer Email totals renderer
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
     * Append order totals with gift card row to shipment notification emails.
     *
     * @param Items $subject Shipment email items block
     * @param string $result Rendered HTML
     * @return string
     */
    public function afterToHtml(Items $subject, string $result): string
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        $order = $subject->getOrder();
        if ($order === null || !$this->applier->orderHasGiftCard($order)) {
            return $result;
        }

        $totalsHtml = $this->totalsRenderer->render($subject->getLayout(), $order);
        if ($totalsHtml === '') {
            return $result;
        }

        return $result . $totalsHtml;
    }
}
