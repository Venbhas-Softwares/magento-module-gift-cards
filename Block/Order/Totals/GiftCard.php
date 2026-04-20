<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Order\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template;
use Venbhas\GiftCard\Model\Config;

/**
 * Order totals block for displaying the gift card discount total.
 */
class GiftCard extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * Initialize block.
     *
     * @param Template\Context $context Block context
     * @param Config $config Module config
     * @param array $data Additional data
     */
    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Add gift card total to order totals.
     *
     * @return AbstractBlock
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        if (!$parent) {
            return $this;
        }
        $order = $parent->getOrder();
        if (!$order) {
            return $this;
        }

        $amount = (float) $order->getData('venbhas_giftcard_amount');
        if ($amount <= 0.0001) {
            return $this;
        }

        $storeId = (int) $order->getStoreId();
        $title = $this->config->getTotalTitle($storeId);
        $baseAmount = (float) $order->getData('base_venbhas_giftcard_amount');
        if ($baseAmount <= 0.0001) {
            $baseAmount = $amount;
        }
        $total = new DataObject([
            'code' => 'venbhas_giftcard',
            'label' => $title,
            'value' => -$amount,
            'base_value' => -$baseAmount,
        ]);
        $parent->addTotal($total, 'discount');

        return $this;
    }
}
