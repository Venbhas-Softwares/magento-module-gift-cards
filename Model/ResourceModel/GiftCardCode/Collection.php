<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\ResourceModel\GiftCardCode;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Venbhas\GiftCard\Model\GiftCardCode as Model;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode as ResourceModel;

/**
 * Gift card code collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Initialize collection.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
