<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction as ResourceModel;

class Collection extends SearchResult
{
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        $mainTable = 'venbhas_giftcard_transaction',
        $resourceModel = ResourceModel::class
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    protected function _initSelect()
    {
        parent::_initSelect();

        $gcTable = $this->getTable('venbhas_giftcard_code');
        $this->getSelect()->joinLeft(
            ['gc' => $gcTable],
            'main_table.giftcard_id = gc.entity_id',
            ['giftcard_code' => 'gc.code', 'gc_currency' => 'gc.currency_code']
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

