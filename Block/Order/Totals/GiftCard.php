<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Order\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template;
use Venbhas\GiftCard\Model\Config;

class GiftCard extends Template
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
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
        $total = new DataObject([
            'code' => 'venbhas_giftcard',
            'label' => $title,
            'value' => -$amount,
        ]);
        $parent->addTotal($total, 'discount');

        return $this;
    }
}

