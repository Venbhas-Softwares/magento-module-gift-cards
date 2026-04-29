<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Adminhtml\Order\Creditmemo\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\App\ResourceConnection;

class GiftCard extends Template
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param Template\Context $context Block context
     * @param ResourceConnection $resource Resource connection
     * @param array $data Additional data
     */
    public function __construct(
        Template\Context $context,
        ResourceConnection $resource,
        array $data = []
    ) {
        $this->resource = $resource;
        parent::__construct($context, $data);
    }

    /**
     * Add Gift Card total row to creditmemo totals table.
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

        // After the creditmemo is saved/reloaded, these values are not persisted (no native columns).
        // Fallback: read the credited-back amount for this creditmemo from our transaction ledger.
        if ($baseAmount <= 0.0001 && $amount <= 0.0001) {
            $order = method_exists($source, 'getOrder') ? $source->getOrder() : null;
            $orderId = $order ? (int) $order->getEntityId() : 0;
            $creditmemoId = (int) ($source->getEntityId() ?: 0);

            if ($orderId > 0 && $creditmemoId > 0) {
                $desc = sprintf('order refunded (creditmemo %d)', $creditmemoId);
                $conn = $this->resource->getConnection();
                $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');
                $credited = (float) $conn->fetchOne(
                    'SELECT COALESCE(SUM(amount),0) FROM ' . $trxTable
                    . ' WHERE order_id = ? AND transaction_type = ? AND description = ?',
                    [$orderId, 'credit', $desc]
                );
                if ($credited > 0.0001) {
                    $baseAmount = $credited;
                    $amount = $credited;
                }
            }
        }

        // If there is no gift card discount on this creditmemo, don't show the row.
        if ($baseAmount <= 0.0001 && $amount <= 0.0001) {
            return $this;
        }

        $total = new DataObject([
            'code' => 'venbhas_giftcard',
            'value' => -abs($amount ?: $baseAmount),
            'base_value' => -abs($baseAmount ?: $amount),
            'label' => __('Gift Card'),
        ]);

        // Place it before grand total; after tax if present.
        $after = $parent->getTotal('tax') ? 'tax' : null;
        $parent->addTotal($total, $after);

        return $this;
    }
}
