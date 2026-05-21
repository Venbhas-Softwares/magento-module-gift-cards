<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Order;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Order\Totals;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\GiftCardTransactionDescription;

/**
 * Adds the gift card discount row to Magento sales totals blocks (order, invoice, credit memo).
 */
class GiftCardTotalsApplier
{
    private const TOTAL_CODE = 'venbhas_giftcard';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param Config $config Module config
     * @param ResourceConnection $resource DB connection
     */
    public function __construct(
        Config $config,
        ResourceConnection $resource
    ) {
        $this->config = $config;
        $this->resource = $resource;
    }

    /**
     * Whether the order has a wallet gift card amount applied.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function orderHasGiftCard(OrderInterface $order): bool
    {
        return $this->resolveAmounts($order) !== null;
    }

    /**
     * Append gift card total to a sales totals block when applicable.
     *
     * @param Totals $totalsBlock
     * @return void
     */
    public function apply(Totals $totalsBlock): void
    {
        if ($totalsBlock->getTotal(self::TOTAL_CODE)) {
            return;
        }

        $source = $totalsBlock->getSource() ?? $this->resolveSourceFromBlock($totalsBlock);
        if ($source === null) {
            return;
        }

        $amounts = $this->resolveAmounts($source);
        if ($amounts === null) {
            return;
        }

        $storeId = $this->resolveStoreId($source);
        $title = $this->config->getTotalTitle($storeId);

        $total = new DataObject([
            'code' => self::TOTAL_CODE,
            'label' => $title,
            'value' => -$amounts['amount'],
            'base_value' => -$amounts['base_amount'],
            'area' => '',
        ]);

        $after = $this->resolveInsertAfter($totalsBlock);
        $totalsBlock->addTotal($total, $after);
    }

    /**
     * Resolve gift card amounts from order, invoice, or credit memo.
     *
     * @param Order|Invoice|Creditmemo|OrderInterface|object $source
     * @return array{amount: float, base_amount: float}|null
     */
    public function resolveAmounts($source): ?array
    {
        if ($source instanceof Creditmemo) {
            return $this->resolveCreditmemoAmounts($source);
        }

        if ($source instanceof Invoice) {
            return $this->resolveInvoiceAmounts($source);
        }

        if ($source instanceof Order || $source instanceof OrderInterface) {
            $amounts = $this->resolveDocumentAmounts(
                (float) ($source->getData('venbhas_giftcard_amount') ?? 0),
                (float) ($source->getData('base_venbhas_giftcard_amount') ?? 0)
            );
            if ($amounts !== null) {
                return $amounts;
            }

            return $this->resolveInferredAmounts($source);
        }

        if (is_object($source) && method_exists($source, 'getData')) {
            return $this->resolveDocumentAmounts(
                (float) ($source->getData('venbhas_giftcard_amount') ?? 0),
                (float) ($source->getData('base_venbhas_giftcard_amount') ?? 0)
            );
        }

        return null;
    }

    /**
     * Resolve gift card amounts for an invoice.
     *
     * @param Invoice $invoice
     * @return array{amount: float, base_amount: float}|null
     */
    private function resolveInvoiceAmounts(Invoice $invoice): ?array
    {
        $amounts = $this->resolveDocumentAmounts(
            (float) ($invoice->getData('venbhas_giftcard_amount') ?? 0),
            (float) ($invoice->getData('base_venbhas_giftcard_amount') ?? 0)
        );
        if ($amounts !== null) {
            return $amounts;
        }

        $order = $invoice->getOrder();
        if ($order === null) {
            return null;
        }

        $orderGift = (float) (
            $order->getData('base_venbhas_giftcard_amount')
            ?? $order->getData('venbhas_giftcard_amount')
            ?? 0
        );
        if ($orderGift <= 0.0001) {
            return null;
        }

        $computed = $this->computeRemainingInvoiceGiftAmount($invoice, $order, $orderGift);
        if ($computed > 0.0001) {
            return [
                'amount' => $computed,
                'base_amount' => $computed,
            ];
        }

        return $this->resolveInferredAmounts($invoice, $order);
    }

    /**
     * Resolve gift card amounts for a credit memo.
     *
     * @param Creditmemo $creditmemo
     * @return array{amount: float, base_amount: float}|null
     */
    private function resolveCreditmemoAmounts(Creditmemo $creditmemo): ?array
    {
        $amount = (float) ($creditmemo->getData('venbhas_giftcard_amount') ?? 0);
        $baseAmount = (float) ($creditmemo->getData('base_venbhas_giftcard_amount') ?? 0);

        if ($baseAmount <= 0.0001 && $amount <= 0.0001) {
            $order = $creditmemo->getOrder();
            $orderId = $order ? (int) $order->getEntityId() : 0;
            $creditmemoId = (int) ($creditmemo->getEntityId() ?: 0);

            if ($orderId > 0 && $creditmemoId > 0) {
                $incrementId = $order ? (string) $order->getIncrementId() : '';
                $desc = GiftCardTransactionDescription::creditedForRefund($incrementId, $creditmemoId);
                $conn = $this->resource->getConnection();
                $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');
                $credited = (float) $conn->fetchOne(
                    'SELECT COALESCE(SUM(amount),0) FROM ' . $trxTable
                    . ' WHERE order_id = ? AND transaction_type = ? AND description = ?',
                    [$orderId, GiftCardTransaction::TYPE_CREDIT, $desc]
                );
                if ($credited > 0.0001) {
                    $baseAmount = $credited;
                    $amount = $credited;
                }
            }
        }

        $amounts = $this->resolveDocumentAmounts($amount, $baseAmount);
        if ($amounts !== null) {
            return $amounts;
        }

        $order = $creditmemo->getOrder();
        if ($order === null) {
            return null;
        }

        return $this->resolveInferredAmounts($creditmemo, $order);
    }

    /**
     * Match invoice collector logic when DB columns were not persisted on the invoice row.
     *
     * @param Invoice $invoice
     * @param Order $order
     * @param float $orderGift
     * @return float
     */
    private function computeRemainingInvoiceGiftAmount(Invoice $invoice, Order $order, float $orderGift): float
    {
        $orderId = (int) $order->getEntityId();
        if ($orderId <= 0) {
            return 0.0;
        }

        $conn = $this->resource->getConnection();
        $invoiceTable = $this->resource->getTableName('sales_invoice');
        $invoiceColumns = array_keys((array) $conn->describeTable($invoiceTable));

        $alreadyInvoicedGift = 0.0;
        if (in_array('base_venbhas_giftcard_amount', $invoiceColumns, true)) {
            $currentInvoiceId = (int) ($invoice->getEntityId() ?: 0);
            // phpcs:disable Magento2.SQL.RawQuery -- dynamic table name with bound parameters
            $sql = 'SELECT COALESCE(SUM(base_venbhas_giftcard_amount),0) FROM '
                . $invoiceTable
                . ' WHERE order_id = ?';
            $bind = [$orderId];
            if ($currentInvoiceId > 0) {
                $sql .= ' AND entity_id <> ?';
                $bind[] = $currentInvoiceId;
            }
            $alreadyInvoicedGift = (float) $conn->fetchOne($sql, $bind);
            // phpcs:enable Magento2.SQL.RawQuery
        }

        $remaining = max(0.0, $orderGift - $alreadyInvoicedGift);
        if ($remaining <= 0.0001) {
            return 0.0;
        }

        $baseGrand = (float) $invoice->getBaseGrandTotal();
        if ($baseGrand <= 0.0001) {
            return 0.0;
        }

        return min($remaining, $baseGrand);
    }

    /**
     * Infer gift amount from totals breakdown when custom columns are empty.
     *
     * @param Invoice|Creditmemo|Order|OrderInterface $document
     * @param Order|null $order Parent order for validation
     * @return array{amount: float, base_amount: float}|null
     */
    private function resolveInferredAmounts($document, ?Order $order = null): ?array
    {
        if ($order === null && $document instanceof Order) {
            $order = $document;
        }

        if ($order === null) {
            return null;
        }

        $orderGift = (float) (
            $order->getData('base_venbhas_giftcard_amount')
            ?? $order->getData('venbhas_giftcard_amount')
            ?? 0
        );
        if ($orderGift <= 0.0001) {
            return null;
        }

        $inferred = $this->inferGiftAmountFromTotals($document);
        if ($inferred <= 0.0001) {
            return null;
        }

        // Cap at order gift amount to avoid mis-labeling other discounts.
        $amount = min($inferred, $orderGift);

        return [
            'amount' => $amount,
            'base_amount' => $amount,
        ];
    }

    /**
     * Infer gift amount from totals breakdown when custom columns are empty.
     *
     * @param Invoice|Creditmemo|Order|OrderInterface $document
     * @return float
     */
    private function inferGiftAmountFromTotals($document): float
    {
        $subtotal = (float) $document->getSubtotal();
        $shipping = (float) ($document->getShippingAmount() ?? 0);
        $tax = (float) ($document->getTaxAmount() ?? 0);
        $discount = (float) ($document->getDiscountAmount() ?? 0);
        $grand = (float) $document->getGrandTotal();

        $adjustment = 0.0;
        if (method_exists($document, 'getAdjustmentPositive')) {
            $adjustment += (float) ($document->getAdjustmentPositive() ?? 0);
        }
        if (method_exists($document, 'getAdjustmentNegative')) {
            $adjustment -= (float) ($document->getAdjustmentNegative() ?? 0);
        }

        $computed = $subtotal + $shipping + $tax + $discount + $adjustment - $grand;

        return $computed > 0.0001 ? round($computed, 4) : 0.0;
    }

    /**
     * Normalize amount and base amount into a display pair.
     *
     * @param float $amount
     * @param float $baseAmount
     * @return array{amount: float, base_amount: float}|null
     */
    private function resolveDocumentAmounts(float $amount, float $baseAmount): ?array
    {
        if ($baseAmount <= 0.0001 && $amount <= 0.0001) {
            return null;
        }

        if ($baseAmount <= 0.0001) {
            $baseAmount = $amount;
        }
        if ($amount <= 0.0001) {
            $amount = $baseAmount;
        }

        return [
            'amount' => abs($amount),
            'base_amount' => abs($baseAmount),
        ];
    }

    /**
     * Resolve invoice/credit memo/order when getSource() is not populated yet.
     *
     * @param Totals $totalsBlock
     * @return Invoice|Creditmemo|Order|OrderInterface|null
     */
    private function resolveSourceFromBlock(Totals $totalsBlock)
    {
        if (method_exists($totalsBlock, 'getInvoice')) {
            $invoice = $totalsBlock->getInvoice();
            if ($invoice) {
                return $invoice;
            }
        }

        if (method_exists($totalsBlock, 'getCreditmemo')) {
            $creditmemo = $totalsBlock->getCreditmemo();
            if ($creditmemo) {
                return $creditmemo;
            }
        }

        if (method_exists($totalsBlock, 'getOrder')) {
            try {
                return $totalsBlock->getOrder();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Determine which total row the gift card line should follow.
     *
     * @param Totals $totalsBlock
     * @return string|null
     */
    private function resolveInsertAfter(Totals $totalsBlock): ?string
    {
        foreach (['discount', 'tax', 'shipping'] as $code) {
            if ($totalsBlock->getTotal($code)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Resolve store ID from order, invoice, or credit memo.
     *
     * @param Order|Invoice|Creditmemo|object $source
     * @return int|null
     */
    private function resolveStoreId($source): ?int
    {
        if ($source instanceof Creditmemo || $source instanceof Invoice) {
            $order = $source->getOrder();
            return $order ? (int) $order->getStoreId() : null;
        }

        if ($source instanceof Order || $source instanceof OrderInterface) {
            return (int) $source->getStoreId();
        }

        if (is_object($source) && method_exists($source, 'getStoreId')) {
            return (int) $source->getStoreId();
        }

        return null;
    }
}
