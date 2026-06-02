<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;

/**
 * Base observer: no-op when the module is disabled in store configuration.
 */
abstract class AbstractObserver implements ObserverInterface
{
    /**
     * @var ModuleEnabledGuard
     */
    private ModuleEnabledGuard $moduleEnabledGuard;

    /**
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     */
    public function __construct(ModuleEnabledGuard $moduleEnabledGuard)
    {
        $this->moduleEnabledGuard = $moduleEnabledGuard;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        if (!$this->moduleEnabledGuard->isEnabled($this->resolveStoreId($observer))) {
            return;
        }

        $this->executeWhenEnabled($observer);
    }

    /**
     * Run observer logic when the module is enabled for the resolved store.
     *
     * @param Observer $observer Observer
     *
     * @return void
     */
    abstract protected function executeWhenEnabled(Observer $observer): void;

    /**
     * Resolve store ID from common observer event payloads.
     *
     * @param Observer $observer Observer
     *
     * @return int|null
     */
    protected function resolveStoreId(Observer $observer): ?int
    {
        $event = $observer->getEvent();

        $product = $observer->getData('product') ?: $event->getData('product');
        if ($product !== null && method_exists($product, 'getStoreId')) {
            $storeId = (int) $product->getStoreId();
            if ($storeId > 0) {
                return $storeId;
            }
        }

        $quoteItem = $observer->getData('quote_item');
        if ($quoteItem !== null && method_exists($quoteItem, 'getQuote')) {
            $quote = $quoteItem->getQuote();
            if ($quote !== null && method_exists($quote, 'getStoreId')) {
                return (int) $quote->getStoreId();
            }
        }

        $items = $observer->getData('items');
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item !== null && method_exists($item, 'getQuote')) {
                    $quote = $item->getQuote();
                    if ($quote !== null && method_exists($quote, 'getStoreId')) {
                        return (int) $quote->getStoreId();
                    }
                }
            }
        }

        $order = $observer->getData('order') ?: $event->getOrder();
        if ($order !== null && method_exists($order, 'getStoreId')) {
            return (int) $order->getStoreId();
        }

        $quote = $event->getQuote();
        if ($quote !== null && method_exists($quote, 'getStoreId')) {
            return (int) $quote->getStoreId();
        }

        $invoice = $observer->getData('invoice');
        if ($invoice !== null) {
            if (method_exists($invoice, 'getStoreId')) {
                $storeId = (int) $invoice->getStoreId();
                if ($storeId > 0) {
                    return $storeId;
                }
            }
            if (method_exists($invoice, 'getOrder')) {
                $invoiceOrder = $invoice->getOrder();
                if ($invoiceOrder !== null && method_exists($invoiceOrder, 'getStoreId')) {
                    return (int) $invoiceOrder->getStoreId();
                }
            }
        }

        $creditmemo = $observer->getData('creditmemo') ?: $event->getCreditmemo();
        if ($creditmemo !== null && method_exists($creditmemo, 'getOrder')) {
            $creditmemoOrder = $creditmemo->getOrder();
            if ($creditmemoOrder !== null && method_exists($creditmemoOrder, 'getStoreId')) {
                return (int) $creditmemoOrder->getStoreId();
            }
        }

        $dataObject = $event->getDataObject();
        if ($dataObject !== null && method_exists($dataObject, 'getStoreId')) {
            return (int) $dataObject->getStoreId();
        }

        return null;
    }
}
