<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Total\Quote;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Venbhas\GiftCard\Model\Config;

/**
 * Quote total collector for gift card discount.
 */
class GiftCard extends AbstractTotal
{
    public const CODE = 'venbhas_giftcard';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * Initialize total collector.
     *
     * @param GiftCardManager $giftCardManager Gift card manager
     * @param CodeCollectionFactory $codeCollectionFactory Gift card code collection factory
     * @param Config $config Module config
     * @param Json $json JSON serializer
     * @param GiftCardRedeemValidator $redeemValidator Redeem validator
     */
    public function __construct(
        Config $config,
        Json $json,
        ResourceConnection $resource
    ) {
        $this->config = $config;
        $this->json = $json;
        $this->resource = $resource;
        $this->setCode(self::CODE);
    }

    /**
     * Collect gift card total for the quote.
     *
     * @param Quote $quote Quote
     * @param ShippingAssignmentInterface $shippingAssignment Shipping assignment
     * @param Total $total Total
     *
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        if (!$this->config->isEnabled((int) $quote->getStoreId())) {
            return $this;
        }

        $requestedBase = (float) ($quote->getData('base_venbhas_giftcard_amount') ?? 0);
        if ($requestedBase <= 0.0001) {
            return $this;
        }

        $baseGrandTotal = (float) $total->getBaseGrandTotal();
        if ($baseGrandTotal <= 0.0001) {
            $baseGrandTotal = (float) $total->getData('base_subtotal_with_discount')
                + (float) $total->getData('base_shipping_amount')
                + (float) $total->getData('base_tax_amount');
        }
        if ($baseGrandTotal <= 0.0001) {
            return $this;
        }

        $walletBalance = $this->getWalletBalance($quote);
        $baseToApply = min($requestedBase, $walletBalance, $baseGrandTotal);
        if ($baseToApply <= 0.0001) {
            // Nothing available; clear request so UI reflects reality.
            $quote->setData('venbhas_giftcard_amount', null);
            $quote->setData('base_venbhas_giftcard_amount', null);
            return $this;
        }

        $total->addTotalAmount(self::CODE, -$toApply);
        $total->addBaseTotalAmount(self::CODE, -$baseToApply);
        $total->setGrandTotal((float) $total->getGrandTotal() - $toApply);
        $total->setBaseGrandTotal((float) $total->getBaseGrandTotal() - $baseToApply);

        $total->setData('venbhas_giftcard_amount', $toApply);
        $total->setData('base_venbhas_giftcard_amount', $baseToApply);
        $quote->setData('venbhas_giftcard_amount', $toApply);
        $quote->setData('base_venbhas_giftcard_amount', $baseToApply);

        return $this;
    }

    private function getWalletBalance(Quote $quote): float
    {
        $customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : 0;
        $email = strtolower(trim((string) $quote->getCustomerEmail()));
        $email = $email !== '' ? $email : null;
        if ($customerId <= 0 && !$email) {
            return 0.0;
        }

        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');

        $where = [];
        if ($customerId > 0) {
            $where[] = 'customer_id = ' . (int) $customerId;
        }
        if ($email) {
            $where[] = 'customer_email = ' . $conn->quote($email);
        }

        $sql = 'SELECT COALESCE(SUM(CASE '
            . 'WHEN transaction_type = ' . $conn->quote(\Venbhas\GiftCard\Model\GiftCardTransaction::ACTION_CREDIT) . ' THEN amount '
            . 'WHEN transaction_type = ' . $conn->quote(\Venbhas\GiftCard\Model\GiftCardTransaction::ACTION_CHECKOUT_APPLY) . ' THEN -amount '
            . 'WHEN transaction_type = ' . $conn->quote(\Venbhas\GiftCard\Model\GiftCardTransaction::ACTION_REDEEM) . ' THEN -amount '
            . 'ELSE 0 END), 0) '
            . 'FROM ' . $trxTable . ' WHERE (' . implode(' OR ', $where) . ')';

        return max(0.0, (float) $conn->fetchOne($sql));
    }

    /**
     * Fetch total row data for display.
     *
     * @param Quote $quote Quote
     * @param Total $total Total
     *
     * @return array<string, mixed>
     */
    public function fetch(Quote $quote, Total $total)
    {
        $amount = (float) $quote->getData('venbhas_giftcard_amount');
        if ($amount <= 0.0001) {
            return [];
        }
        $title = $this->config->getTotalTitle((int) $quote->getStoreId());

        return [
            'code' => self::CODE,
            'title' => $title,
            'value' => -$amount,
            'venbhas_giftcard_codes' => (string) $quote->getData('venbhas_giftcard_codes'),
        ];
    }
}
