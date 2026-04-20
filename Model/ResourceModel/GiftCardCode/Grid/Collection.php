<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode as ResourceModel;

/**
 * Admin grid collection for gift card codes.
 */
class Collection extends SearchResult
{
    /**
     * Initialize grid collection with default table mapping.
     *
     * Magento UI data providers may instantiate this class without passing `$mainTable` /
     * `$resourceModel`, so we provide safe defaults here.
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
        $mainTable = $mainTable ?: 'venbhas_giftcard_code';
        $resourceModel = $resourceModel ?: ResourceModel::class;

        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }
}
