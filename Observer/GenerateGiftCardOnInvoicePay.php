<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Venbhas\GiftCard\Model\Email\GiftCardSender;
use Venbhas\GiftCard\Model\GiftCardIssuer;

/**
 * Observer to issue gift cards when an invoice is paid.
 */
class GenerateGiftCardOnInvoicePay implements ObserverInterface
{
    /**
     * @var GiftCardIssuer
     */
    private $issuer;

    /**
     * @var GiftCardSender
     */
    private $sender;

    /**
     * @param GiftCardIssuer $issuer Gift card issuer
     * @param GiftCardSender $sender Gift card email sender
     */
    public function __construct(
        GiftCardIssuer $issuer,
        GiftCardSender $sender
    ) {
        $this->issuer = $issuer;
        $this->sender = $sender;
    }

    /**
     * Generate and email gift cards when an invoice is paid.
     *
     * @param Observer $observer Observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var InvoiceInterface|null $invoice */
        $invoice = $observer->getData('invoice');
        if (!$invoice) {
            return;
        }

        $order = $invoice->getOrder();
        if (!$order) {
            return;
        }

        foreach ($invoice->getItems() as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();
            if (!$orderItem) {
                continue;
            }
            $qtyThisInvoice = (int) max(0, (float) $invoiceItem->getQty());
            if ($qtyThisInvoice < 1) {
                continue;
            }
            $codes = $this->issuer->issueForOrderItem($order, $orderItem, $qtyThisInvoice);
            foreach ($codes as $code) {
                $this->sender->send($code);
            }
        }
    }
}
