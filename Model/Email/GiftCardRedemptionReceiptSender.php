<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Email;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as GiftCardCodeCollectionFactory;

/**
 * Sends a receipt to the customer when an invoice is paid and gift cards were applied to the order.
 */
class GiftCardRedemptionReceiptSender
{
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var GiftCardCodeCollectionFactory
     */
    private $codeCollectionFactory;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * Initialize sender.
     *
     * @param TransportBuilder $transportBuilder Transport builder
     * @param StoreManagerInterface $storeManager Store manager
     * @param LoggerInterface $logger Logger
     * @param PriceCurrencyInterface $priceCurrency Price currency formatter
     * @param Json $json JSON serializer
     * @param GiftCardCodeCollectionFactory $codeCollectionFactory Gift card code collection factory
     * @param Escaper $escaper Escaper
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        PriceCurrencyInterface $priceCurrency,
        Json $json,
        GiftCardCodeCollectionFactory $codeCollectionFactory,
        Escaper $escaper
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->priceCurrency = $priceCurrency;
        $this->json = $json;
        $this->codeCollectionFactory = $codeCollectionFactory;
        $this->escaper = $escaper;
    }

    /**
     * Send receipt email for an order.
     *
     * @param OrderInterface $order Order
     *
     * @return void
     */
    public function sendForOrder(OrderInterface $order): void
    {
        $toEmail = trim((string) $order->getCustomerEmail());
        if ($toEmail === '') {
            return;
        }

        $raw = (string) $order->getData('venbhas_giftcard_applied');
        if ($raw === '') {
            return;
        }

        try {
            $applied = $this->json->unserialize($raw);
        } catch (\Exception $e) {
            return;
        }

        if (!is_array($applied) || $applied === []) {
            return;
        }

        $codesUpper = [];
        foreach ($applied as $row) {
            $c = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($c !== '') {
                $codesUpper[] = $c;
            }
        }
        $codesUpper = array_values(array_unique($codesUpper));
        if ($codesUpper === []) {
            return;
        }

        $collection = $this->codeCollectionFactory->create();
        $collection->addFieldToFilter('code', ['in' => $codesUpper]);

        $byCode = [];
        foreach ($collection->getItems() as $gc) {
            $byCode[strtoupper((string) $gc->getData('code'))] = $gc;
        }

        $headerCode = $this->escaper->escapeHtml((string) __('Gift card code'));
        $headerUsed = $this->escaper->escapeHtml((string) __('Amount used'));
        $headerInitial = $this->escaper->escapeHtml((string) __('Initial amount'));
        $headerBalance = $this->escaper->escapeHtml((string) __('Current balance'));

        $rowsHtml = '';
        foreach ($applied as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $amountUsed = (float) ($row['base_amount'] ?? 0);
            if ($code === '' || $amountUsed <= 0.0001) {
                continue;
            }
            $gc = $byCode[$code] ?? null;
            if ($gc === null) {
                continue;
            }

            $currency = (string) $gc->getData('currency_code');
            $initial = (float) ($gc->getData('initial_value') ?? 0);
            $balance = $this->readBalanceAmount($gc);

            $rowsHtml .= '<tr>'
                . '<td style="padding:8px;border:1px solid #e3e3e3;">'
                . $this->escaper->escapeHtml($code) . '</td>'
                . '<td style="padding:8px;border:1px solid #e3e3e3;">'
                . $this->escaper->escapeHtml(
                    $this->priceCurrency->format(
                        $amountUsed,
                        false,
                        PriceCurrencyInterface::DEFAULT_PRECISION,
                        null,
                        $currency
                    )
                ) . '</td>'
                . '<td style="padding:8px;border:1px solid #e3e3e3;">'
                . $this->escaper->escapeHtml(
                    $this->priceCurrency->format(
                        $initial,
                        false,
                        PriceCurrencyInterface::DEFAULT_PRECISION,
                        null,
                        $currency
                    )
                ) . '</td>'
                . '<td style="padding:8px;border:1px solid #e3e3e3;">'
                . $this->escaper->escapeHtml(
                    $this->priceCurrency->format(
                        $balance,
                        false,
                        PriceCurrencyInterface::DEFAULT_PRECISION,
                        null,
                        $currency
                    )
                ) . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            return;
        }

        $customerName = trim((string) $order->getCustomerName());
        if ($customerName === '') {
            $billing = $order->getBillingAddress();
            $customerName = $billing ? trim((string) $billing->getName()) : '';
        }
        if ($customerName === '') {
            $customerName = (string) __('Valued Customer');
        }

        $tableHtml = '<table style="border-collapse:collapse;width:100%;max-width:640px;margin-top:12px;">'
            . '<thead><tr style="background:#f6f6f6;">'
            . '<th style="padding:8px;border:1px solid #e3e3e3;text-align:left;">' . $headerCode . '</th>'
            . '<th style="padding:8px;border:1px solid #e3e3e3;text-align:left;">' . $headerUsed . '</th>'
            . '<th style="padding:8px;border:1px solid #e3e3e3;text-align:left;">' . $headerInitial . '</th>'
            . '<th style="padding:8px;border:1px solid #e3e3e3;text-align:left;">' . $headerBalance . '</th>'
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';

        $storeId = (int) $order->getStoreId();

        $transport = $this->transportBuilder
            ->setTemplateIdentifier('venbhas_giftcard_redemption_receipt_template')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars([
                'customer_name' => $customerName,
                'order_increment_id' => (string) $order->getIncrementId(),
                'redemption_table_html' => $tableHtml,
            ])
            ->setFromByScope('general', $storeId)
            ->addTo($toEmail, $customerName)
            ->getTransport();

        try {
            $transport->sendMessage();
        } catch (MailException $e) {
            $this->logger->error(
                'Venbhas GiftCard: could not send gift card redemption receipt email.',
                [
                    'recipient' => $toEmail,
                    'order_id' => $order->getEntityId(),
                    'exception' => $e,
                ]
            );
        }
    }

    /**
     * Read gift card balance amount from a gift card entity.
     *
     * @param \Magento\Framework\DataObject $gc
     *
     * @return float
     */
    private function readBalanceAmount($gc): float
    {
        foreach (['balance_amount', 'amount', 'balance'] as $attr) {
            $v = $gc->getData($attr);
            if ($v !== null && $v !== '') {
                return (float) $v;
            }
        }

        return 0.0;
    }
}
