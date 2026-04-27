<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction as ResourceModel;

/**
 * Admin grid collection for gift card transactions.
 */
class Collection extends SearchResult
{
    /**
     * Initialize grid collection with default table mapping.
     *
     * Some UI data providers instantiate this collection without passing `$mainTable` /
     * `$resourceModel`. Provide defaults to avoid empty table names at runtime.
     *
     * @param EntityFactory $entityFactory Entity factory
     * @param Logger $logger Logger
     * @param FetchStrategy $fetchStrategy Fetch strategy
     * @param EventManager $eventManager Event manager
     * @param string|null $mainTable Main table
     * @param string|null $resourceModel Resource model
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        ?string $mainTable = null,
        ?string $resourceModel = null
    ) {
        $mainTable = $mainTable ?: 'venbhas_giftcard_transaction';
        $resourceModel = $resourceModel ?: ResourceModel::class;

        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    /**
     * Initialize select with joins.
     *
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        $gcTable = $this->getTable('venbhas_giftcard_code');
        $this->getSelect()->joinLeft(
            ['gc' => $gcTable],
            'main_table.giftcard_id = gc.entity_id',
            ['giftcard_code' => 'gc.code']
        );

        $soTable = $this->getTable('sales_order');
        $this->getSelect()->joinLeft(
            ['so' => $soTable],
            'main_table.order_id = so.entity_id',
            ['increment_id' => 'so.increment_id']
        );

        return $this;
    }
}
