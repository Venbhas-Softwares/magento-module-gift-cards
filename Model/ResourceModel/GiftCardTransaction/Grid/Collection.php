<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\Grid;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface as Logger;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction as ResourceModel;

/**
 * Admin grid collection for gift card transactions.
 */
class Collection extends SearchResult
{
    /**
     * @var StoreManagerInterface|null
     */
    private $storeManager;

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
     * @param StoreManagerInterface|null $storeManager Store manager
     * @param string|null $mainTable Main table
     * @param string|null $resourceModel Resource model
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        ?StoreManagerInterface $storeManager = null,
        ?string $mainTable = null,
        ?string $resourceModel = null
    ) {
        $mainTable = $mainTable ?: 'venbhas_giftcard_transaction';
        $resourceModel = $resourceModel ?: ResourceModel::class;
        $this->storeManager = $storeManager;

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

        // Customer name (uses flat grid table for performance / non-EAV join complexity).
        $cgTable = $this->getTable('customer_grid_flat');
        $this->getSelect()->joinLeft(
            ['cg' => $cgTable],
            'main_table.customer_id = cg.entity_id',
            ['customer_name' => 'cg.name']
        );

        return $this;
    }

    /**
     * Add `currency_code` field for UI price column formatting.
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();

        $storeManager = $this->storeManager ?: ObjectManager::getInstance()->get(StoreManagerInterface::class);
        foreach ($this->getItems() as $item) {
            $storeId = (int) $item->getData('store_id');
            try {
                $currency = (string) $storeManager->getStore($storeId)->getBaseCurrencyCode();
            } catch (\Throwable $e) {
                $currency = '';
            }
            $item->setData('currency_code', $currency);
        }

        return $this;
    }
}
