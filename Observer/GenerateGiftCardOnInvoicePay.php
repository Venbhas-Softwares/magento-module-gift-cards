<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Venbhas\GiftCard\Model\Email\GiftCardSender;
use Venbhas\GiftCard\Model\GiftCardIssuer;
use Venbhas\GiftCard\Model\GiftCardRedeemer;

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
     * @var GiftCardRedeemer
     */
    private $redeemer;

    public function __construct(
        GiftCardIssuer $issuer,
        GiftCardSender $sender,
        GiftCardRedeemer $redeemer
    ) {
        $this->issuer = $issuer;
        $this->sender = $sender;
        $this->redeemer = $redeemer;
    }

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

        // Redeem applied gift cards (discount) on invoice payment.
        $this->redeemer->redeemOnInvoicePay($order, $invoice);

        foreach ($invoice->getItems() as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();
            if (!$orderItem) {
                continue;
            }
            $qtyThisInvoice = (int)max(0, (float)$invoiceItem->getQty());
            if ($qtyThisInvoice < 1) {
                continue;
            }
            // Activate pending rows (or create legacy rows) for this invoice line only
            $codes = $this->issuer->issueForOrderItem($order, $orderItem, $qtyThisInvoice);
            foreach ($codes as $code) {
                $this->sender->send($code);
            }
        }
    }
}

