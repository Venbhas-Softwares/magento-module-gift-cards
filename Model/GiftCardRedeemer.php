<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Deducts gift card balances and writes "redeem" ledger rows when an invoice is paid.
 * "Applied at checkout" ledger rows are recorded separately at order placement (GiftCardCheckoutTransactionLogger).
 */
class GiftCardRedeemer
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var SalesOrderEntityIdResolver
     */
    private $salesOrderEntityIdResolver;

    /**
     * Initialize redeemer.
     *
     * @param ResourceConnection $resource Resource connection
     * @param Json $json JSON serializer
     * @param SalesOrderEntityIdResolver $salesOrderEntityIdResolver Order ID resolver
     */
    public function __construct(
        ResourceConnection $resource,
        Json $json,
        SalesOrderEntityIdResolver $salesOrderEntityIdResolver
    ) {
        $this->resource = $resource;
        $this->json = $json;
        $this->salesOrderEntityIdResolver = $salesOrderEntityIdResolver;
    }

    /**
     * Deduct balances when an invoice is paid.
     *
     * @param OrderInterface $order Order
     * @param InvoiceInterface $invoice Invoice
     *
     * @return void
     */
    public function redeemOnInvoicePay(OrderInterface $order, InvoiceInterface $invoice): void
    {
        // Redemption has been intentionally disabled for this project.
        // Only order-level amount fields are persisted; gift card balances are not deducted on invoice.
        return;
    }

    /**
     * Execute gift card redemption for an invoice.
     *
     * @param OrderInterface $order Order
     * @param InvoiceInterface $invoice Invoice
     *
     * @return void
     */
    private function executeRedemption(OrderInterface $order, InvoiceInterface $invoice): void
    {
        // Redemption disabled (left for backward compatibility with existing observers/DI wiring).
        return;
    }

    /**
     * Detect which balance column exists in a gift card DB row.
     *
     * @param array $gc Gift card DB row
     *
     * @return string
     */
    private function balanceColumnPresentInRow(array $gc): string
    {
        foreach (['balance_amount', 'amount', 'balance'] as $key) {
            if (array_key_exists($key, $gc)) {
                return $key;
            }
        }

        return 'balance_amount';
    }

    /**
     * Resolve columns to select from gift card code table.
     *
     * @param AdapterInterface $conn DB adapter
     * @param string $codeTable Gift card code table
     *
     * @return array<int, string>
     */
    private function resolveGiftCardSelectColumns(AdapterInterface $conn, string $codeTable): array
    {
        $cols = ['entity_id', 'status', 'redeemer_customer_id', 'redeemer_email'];
        $existing = array_keys((array) $conn->describeTable($codeTable));
        foreach (['balance_amount', 'amount', 'balance'] as $balanceCol) {
            if (in_array($balanceCol, $existing, true)) {
                $cols[] = $balanceCol;
            }
        }

        return array_values(array_unique($cols));
    }

    /**
     * Read current balance from a gift card DB row.
     *
     * @param array $gc Gift card DB row
     *
     * @return float
     */
    private function readBalanceFromGcRow(array $gc): float
    {
        foreach (['balance_amount', 'amount', 'balance'] as $key) {
            if (array_key_exists($key, $gc) && $gc[$key] !== null && $gc[$key] !== '') {
                return (float) $gc[$key];
            }
        }

        return 0.0;
    }

    /**
     * Assert that the order matches any redeemer lock on the gift card.
     *
     * @param OrderInterface $order Order
     * @param array $gc Gift card DB row
     *
     * @return void
     */
    private function assertOrderMatchesRedeemerLock(OrderInterface $order, array $gc): void
    {
        $lockedId = isset($gc['redeemer_customer_id']) ? (int) $gc['redeemer_customer_id'] : 0;
        $lockedEmail = strtolower(trim((string) ($gc['redeemer_email'] ?? '')));
        if ($lockedId <= 0 && $lockedEmail === '') {
            return;
        }
        $orderCustomerId = $order->getCustomerId() ? (int) $order->getCustomerId() : null;
        $orderEmail = strtolower(trim((string) $order->getCustomerEmail()));

        if ($lockedId > 0) {
            if ((int) $orderCustomerId !== $lockedId) {
                throw new LocalizedException(
                    __('Gift card redemption does not match the customer account linked to this card.')
                );
            }

            return;
        }
        if ($orderEmail === '' || $orderEmail !== $lockedEmail) {
            throw new LocalizedException(
                __('Gift card redemption does not match the email linked to this card.')
            );
        }
    }

    /**
     * Decode gift card usage JSON from the order.
     *
     * @param string $raw JSON string
     *
     * @return array<int, array{code: string, base_amount: float}>
     */
    private function decodeApplied(string $raw): array
    {
        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\Exception $e) {
            $decoded = null;
        }
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
