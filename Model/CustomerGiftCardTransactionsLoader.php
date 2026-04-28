<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\ResourceConnection;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\Collection;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\CollectionFactory;

/**
 * Loads gift card transactions tied to a customer ID: direct row customer_id/email,
 * or sales_order.customer_id via join (handles rows inserted without customer_id).
 */
class CustomerGiftCardTransactionsLoader
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * Initialize loader.
     *
     * @param CollectionFactory $collectionFactory Transaction collection factory
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ResourceConnection $resource
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->resource = $resource;
    }

    /**
     * Create a transaction collection for a customer.
     *
     * @param int $customerId Customer ID
     * @param string|null $customerEmail Customer email
     * @param int|null $pageSize Limit rows; omit or null for no explicit limit beyond collection default.
     *
     * @return Collection
     */
    public function createCollection(int $customerId, ?string $customerEmail = null, ?int $pageSize = 200): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->joinGiftCardCode();
        $collection->joinSalesOrder();
        $collection->addFieldToFilter(
            'main_table.transaction_type',
            // Backward compat: older rows used `checkout_apply` before wallet debits were renamed to `debit`.
            ['in' => [GiftCardTransaction::ACTION_REDEEM, GiftCardTransaction::ACTION_DEBIT, GiftCardTransaction::ACTION_CHECKOUT_APPLY, GiftCardTransaction::ACTION_CREDIT]]
        );
        $collection->setOrder('main_table.created_at', 'DESC');
        $collection->setOrder('main_table.entity_id', 'DESC');

        if ($pageSize !== null && $pageSize > 0) {
            $collection->setPageSize($pageSize);
        }

        if ($customerId <= 0) {
            $collection->addFieldToFilter('main_table.entity_id', ['eq' => -1]);

            return $collection;
        }

        $email = $customerEmail !== null ? trim($customerEmail) : '';

        // Build WHERE with quoted literals — Magento\Framework\DB\Select::where() does not reliably
        // bind multiple positional placeholders in one call (extra args may be ignored).
        $conn = $collection->getConnection();
        $cid = (int) $customerId;
        if ($email !== '') {
            $emailQuoted = $conn->quote($email);
            $collection->getSelect()->where(
                '(main_table.customer_id = ' . $cid
                . ' OR main_table.customer_email = ' . $emailQuoted
                . ' OR so.customer_id = ' . $cid . ')'
            );
        } else {
            $collection->getSelect()->where(
                '(main_table.customer_id = ' . $cid . ' OR so.customer_id = ' . $cid . ')'
            );
        }

        return $collection;
    }

    /**
     * Compute wallet balance for a customer (credits - usage).
     *
     * @param int $customerId Customer ID
     * @param string|null $customerEmail Customer email
     *
     * @return float
     */
    public function getWalletBalance(int $customerId, ?string $customerEmail = null): float
    {
        if ($customerId <= 0 && (!$customerEmail || trim($customerEmail) === '')) {
            return 0.0;
        }

        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');

        $cid = (int) $customerId;
        $email = $customerEmail !== null ? strtolower(trim($customerEmail)) : '';

        $where = [];
        if ($cid > 0) {
            $where[] = 'customer_id = ' . $cid;
        }
        if ($email !== '') {
            $where[] = 'customer_email = ' . $conn->quote($email);
        }
        if (!$where) {
            return 0.0;
        }

        $sql = 'SELECT COALESCE(SUM(CASE '
            . 'WHEN transaction_type = ' . $conn->quote(GiftCardTransaction::ACTION_CREDIT) . ' THEN amount '
            . 'WHEN transaction_type = ' . $conn->quote(GiftCardTransaction::ACTION_DEBIT) . ' THEN -amount '
            . 'WHEN transaction_type = ' . $conn->quote(GiftCardTransaction::ACTION_CHECKOUT_APPLY) . ' THEN -amount '
            . 'WHEN transaction_type = ' . $conn->quote(GiftCardTransaction::ACTION_REDEEM) . ' THEN -amount '
            . 'ELSE 0 END), 0) '
            . 'FROM ' . $trxTable . ' WHERE (' . implode(' OR ', $where) . ')';

        return (float) $conn->fetchOne($sql);
    }
}
