<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Venbhas\GiftCard\Model\Email\GiftCardRedemptionReceiptSender;
use Venbhas\GiftCard\Model\Email\GiftCardSender;
use Venbhas\GiftCard\Model\GiftCardIssuer;
use Venbhas\GiftCard\Model\GiftCardRedeemer;

/**
 * Observer to issue gift cards and redeem applied gift cards when an invoice is paid.
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
     * @var GiftCardRedeemer
     */
    private $redeemer;

    /**
     * @var GiftCardRedemptionReceiptSender
     */
    private $redemptionReceiptSender;

    /**
     * Initialize observer.
     *
     * @param GiftCardIssuer $issuer Gift card issuer
     * @param GiftCardSender $sender Gift card email sender
     * @param GiftCardRedeemer $redeemer Gift card redeemer
     * @param GiftCardRedemptionReceiptSender $redemptionReceiptSender Redemption receipt sender
     */
    public function __construct(
        GiftCardIssuer $issuer,
        GiftCardSender $sender,
        GiftCardRedeemer $redeemer,
        GiftCardRedemptionReceiptSender $redemptionReceiptSender
    ) {
        $this->issuer = $issuer;
        $this->sender = $sender;
        $this->redeemer = $redeemer;
        $this->redemptionReceiptSender = $redemptionReceiptSender;
    }

    /**
     * Execute observer.
     *
     * @param Observer $observer Observer
     *
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

        // Redeem applied gift cards (discount) on invoice payment (skipped if already redeemed at order placement).
        $this->redeemer->redeemOnInvoicePay($order, $invoice);

        // Email customer a summary of gift cards applied to this order (code, amount used, initial, balance).
        $this->redemptionReceiptSender->sendForOrder($order);

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
