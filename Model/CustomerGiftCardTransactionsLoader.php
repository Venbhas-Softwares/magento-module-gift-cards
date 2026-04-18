<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

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

    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param int|null $pageSize Limit rows; omit or null for no explicit limit beyond collection default.
     */
    public function createCollection(int $customerId, ?string $customerEmail = null, ?int $pageSize = 200): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->joinGiftCardCode();
        $collection->joinSalesOrder();
        $collection->addFieldToFilter(
            'main_table.action',
            ['in' => [GiftCardTransaction::ACTION_REDEEM, GiftCardTransaction::ACTION_CHECKOUT_APPLY]]
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
}
