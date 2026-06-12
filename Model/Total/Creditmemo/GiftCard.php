<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Total\Creditmemo;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\GiftCardTransactionDescription;

/**
 * Creditmemo total collector: treat wallet gift amount like a discount (not refundable to payment).
 *
 * It reduces creditmemo grand totals by the gift amount portion and stores it on creditmemo data
 * as base_venbhas_giftcard_amount / venbhas_giftcard_amount for observers to credit wallet back.
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
     * Reduce credit memo grand total by remaining gift amount.
     *
     * @param Creditmemo $creditmemo Credit memo
     *
     * @return $this
     */
    public function collect(Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
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

        // Remaining gift amount to be credited back (sum existing "credit back" transactions for this order).
        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');
        $creditedBack = (float) $conn->fetchOne(
            'SELECT COALESCE(SUM(amount),0) FROM ' . $trxTable
            . ' WHERE order_id = ? AND transaction_type = ? AND ('
            . 'description = ? OR description LIKE ? OR description LIKE ?)',
            [
                $orderId,
                GiftCardTransaction::TYPE_CREDIT,
                GiftCardTransactionDescription::LEGACY_CANCEL,
                GiftCardTransactionDescription::LEGACY_REFUND_PREFIX . '%',
                GiftCardTransactionDescription::creditBackLikePattern(),
            ]
        );

        $remaining = max(0.0, $orderGift - $creditedBack);
        if ($remaining <= 0.0001) {
            return $this;
        }

        $baseGrand = (float) $creditmemo->getBaseGrandTotal();
        if ($baseGrand <= 0.0001) {
            return $this;
        }

        $baseToApply = min($remaining, $baseGrand);
        if ($baseToApply <= 0.0001) {
            return $this;
        }

        // Reduce refund totals (so wallet amount is not refunded via payment gateway).
        $creditmemo->setBaseGrandTotal($baseGrand - $baseToApply);
        $creditmemo->setGrandTotal((float) $creditmemo->getGrandTotal() - $baseToApply);

        $creditmemo->setData('base_venbhas_giftcard_amount', $baseToApply);
        $creditmemo->setData('venbhas_giftcard_amount', $baseToApply);

        return $this;
    }
}
