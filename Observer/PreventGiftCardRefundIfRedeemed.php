<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Model\Product\Type\GiftCard as GiftCardType;

class PreventGiftCardRefundIfRedeemed extends AbstractObserver
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param ResourceConnection $resource Resource connection
     */
    public function __construct(ModuleEnabledGuard $moduleEnabledGuard, ResourceConnection $resource)
    {
        parent::__construct($moduleEnabledGuard);
        $this->resource = $resource;
    }

    /**
     * Block refund if issued gift card codes from the order were redeemed.
     *
     * @param Observer $observer Event observer
     *
     * @return void
     */
    protected function executeWhenEnabled(Observer $observer): void
    {
        /** @var CreditmemoInterface|null $creditmemo */
        $creditmemo = $observer->getData('creditmemo') ?: $observer->getEvent()->getCreditmemo();
        if (!$creditmemo instanceof CreditmemoInterface) {
            return;
        }

        $order = $creditmemo->getOrder();
        if (!$order || !(int) $order->getEntityId()) {
            return;
        }

        // Only block when refunding giftcard product items.
        $hasGiftCardItems = false;
        foreach ($creditmemo->getItems() as $cmItem) {
            $orderItem = $cmItem->getOrderItem();
            if ($orderItem && (string) $orderItem->getProductType() === GiftCardType::TYPE_CODE) {
                $hasGiftCardItems = true;
                break;
            }
        }
        if (!$hasGiftCardItems) {
            return;
        }

        $orderId = (int) $order->getEntityId();
        $conn = $this->resource->getConnection();
        $codeTable = $this->resource->getTableName('venbhas_giftcard_code');

        $redeemed = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM ' . $codeTable . ' WHERE order_id = ? AND is_reedemed = 1',
            [$orderId]
        );
        if ($redeemed > 0) {
            throw new LocalizedException(
                __('Refund is not allowed because one or more gift cards from this order were already redeemed.')
            );
        }
    }
}
