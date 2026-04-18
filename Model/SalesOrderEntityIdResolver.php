<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Resolves numeric sales_order.entity_id when observers pass an order without entity_id loaded.
 */
class SalesOrderEntityIdResolver
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function resolve(OrderInterface $order): int
    {
        $conn = $this->resource->getConnection();
        $salesOrderTable = $this->resource->getTableName('sales_order');

        $id = (int) $order->getEntityId();
        if ($id > 0) {
            return $id;
        }

        $incrementId = trim((string) $order->getIncrementId());
        if ($incrementId !== '') {
            $found = (int) $conn->fetchOne(
                'SELECT entity_id FROM ' . $salesOrderTable . ' WHERE increment_id = ? LIMIT 1',
                [$incrementId]
            );
            if ($found > 0) {
                return $found;
            }
        }

        $quoteId = (int) $order->getQuoteId();
        if ($quoteId > 0) {
            $found = (int) $conn->fetchOne(
                'SELECT entity_id FROM ' . $salesOrderTable . ' WHERE quote_id = ? ORDER BY entity_id DESC LIMIT 1',
                [$quoteId]
            );
            if ($found > 0) {
                return $found;
            }
        }

        return 0;
    }
}
