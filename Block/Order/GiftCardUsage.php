<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Order;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Venbhas\GiftCard\Model\GiftCardTransaction;

class GiftCardUsage extends Template
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(
        Context $context,
        Registry $registry,
        Json $json,
        PriceCurrencyInterface $priceCurrency,
        State $appState,
        ResourceConnection $resource,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->json = $json;
        $this->priceCurrency = $priceCurrency;
        $this->appState = $appState;
        $this->resource = $resource;
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('current_order');
        return $order instanceof OrderInterface ? $order : null;
    }

    /**
     * @return array<int, array{code: string, amount: float}>
     */
    public function getUsageRows(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }
        $raw = (string) $order->getData('venbhas_giftcard_usage_details');
        if ($raw === '') {
            $raw = (string) $order->getData('venbhas_giftcard_applied');
        }
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\Exception $e) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $amt = (float) ($row['base_amount'] ?? 0);
            if ($code === '' || $amt <= 0.0001) {
                continue;
            }
            $rows[] = ['code' => $code, 'amount' => $amt];
        }

        if ($rows !== []) {
            return $rows;
        }

        return $this->getUsageRowsFromTransactions($order);
    }

    /**
     * Fallback when order JSON is empty: read from venbhas_giftcard_transaction ledger.
     *
     * @return array<int, array{code: string, amount: float}>
     */
    private function getUsageRowsFromTransactions(OrderInterface $order): array
    {
        $orderId = (int) $order->getEntityId();
        if ($orderId < 1) {
            return [];
        }

        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');
        $codeTable = $this->resource->getTableName('venbhas_giftcard_code');

        $select = $conn->select()
            ->from(['t' => $trxTable], [])
            ->joinLeft(['gc' => $codeTable], 't.giftcard_id = gc.entity_id', [])
            ->where('t.order_id = ?', $orderId)
            ->where('t.action IN (?)', [GiftCardTransaction::ACTION_CHECKOUT_APPLY, GiftCardTransaction::ACTION_REDEEM])
            ->columns([
                'code' => 'gc.code',
                'amount' => new \Zend_Db_Expr('SUM(t.amount)'),
            ])
            ->group('gc.code')
            ->order('gc.code ASC');

        $rows = [];
        foreach ($conn->fetchAll($select) as $r) {
            $code = strtoupper(trim((string) ($r['code'] ?? '')));
            $amt = (float) ($r['amount'] ?? 0);
            if ($code === '' || $amt <= 0.0001) {
                continue;
            }
            $rows[] = ['code' => $code, 'amount' => $amt];
        }

        return $rows;
    }

    public function formatAmount(float $amount): string
    {
        $order = $this->getOrder();
        $currency = $order ? (string) $order->getBaseCurrencyCode() : null;

        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $order ? $order->getStore() : null,
            $currency
        );
    }

    public function getTotalGiftAmount(): float
    {
        $sum = 0.0;
        foreach ($this->getUsageRows() as $row) {
            $sum += $row['amount'];
        }

        return $sum;
    }

    public function isAdminArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML;
        } catch (\Throwable) {
            return false;
        }
    }

    public function shouldDisplay(): bool
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }
        $total = (float) $order->getData('venbhas_giftcard_amount');
        if ($total > 0.0001) {
            return true;
        }

        return $this->getUsageRows() !== [];
    }
}
