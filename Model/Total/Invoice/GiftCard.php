<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Total\Invoice;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
use Venbhas\GiftCard\Model\Config;

/**
 * Invoice total collector: treat gift amount like a discount on invoice.
 */
class GiftCard extends AbstractTotal
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param ResourceConnection $resource Resource connection
     * @param Config $config Module config
     */
    public function __construct(ResourceConnection $resource, Config $config)
    {
        $this->resource = $resource;
        $this->config = $config;
    }

    /**
     * Reduce invoice grand total by remaining gift amount.
     *
     * @param Invoice $invoice Invoice
     *
     * @return $this
     */
    public function collect(Invoice $invoice)
    {
        $order = $invoice->getOrder();
        if (!$order) {
            return $this;
        }

        if (!$this->config->isEnabled((int) $order->getStoreId())) {
            return $this;
        }

        $orderGift = (float) (
            $order->getData('base_venbhas_giftcard_amount')
            ?? $order->getData('venbhas_giftcard_amount')
            ?? 0
        );
        if ($orderGift <= 0.0001) {
            return $this;
        }

        $orderId = (int) $order->getEntityId();
        if ($orderId <= 0) {
            return $this;
        }

        $conn = $this->resource->getConnection();
        $invoiceTable = $this->resource->getTableName('sales_invoice');
        $invoiceColumns = array_keys((array) $conn->describeTable($invoiceTable));

        // If invoice-level columns exist, compute remaining amount for partial invoices.
        $alreadyInvoicedGift = 0.0;
        if (in_array('base_venbhas_giftcard_amount', $invoiceColumns, true)) {
            $currentInvoiceId = (int) ($invoice->getEntityId() ?: 0);
            $sql =
                // phpcs:ignore Magento2.SQL.RawQuery.RawQuery
                'SELECT COALESCE(SUM(base_venbhas_giftcard_amount),0) FROM '
                . $invoiceTable
                . ' WHERE order_id = ?';
            $bind = [$orderId];
            if ($currentInvoiceId > 0) {
                $sql .= ' AND entity_id <> ?';
                $bind[] = $currentInvoiceId;
            }
            $alreadyInvoicedGift = (float) $conn->fetchOne($sql, $bind);
        }

        $remaining = max(0.0, $orderGift - $alreadyInvoicedGift);
        if ($remaining <= 0.0001) {
            return $this;
        }

        $baseGrand = (float) $invoice->getBaseGrandTotal();
        if ($baseGrand <= 0.0001) {
            return $this;
        }

        $baseToApply = min($remaining, $baseGrand);
        if ($baseToApply <= 0.0001) {
            return $this;
        }

        $invoice->setBaseGrandTotal($baseGrand - $baseToApply);
        $invoice->setGrandTotal((float) $invoice->getGrandTotal() - $baseToApply);

        $invoice->setData('base_venbhas_giftcard_amount', $baseToApply);
        $invoice->setData('venbhas_giftcard_amount', $baseToApply);

        return $this;
    }
}
