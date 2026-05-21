<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Total\Quote;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\CustomerGiftCardTransactionsLoader;

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
     * @var CustomerGiftCardTransactionsLoader
     */
    private $walletLoader;

    /**
     * @param Config $config Module config
     * @param CustomerGiftCardTransactionsLoader $walletLoader Wallet balance loader
     */
    public function __construct(
        Config $config,
        CustomerGiftCardTransactionsLoader $walletLoader
    ) {
        $this->config = $config;
        $this->walletLoader = $walletLoader;
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

        $address = $shippingAssignment->getShipping()->getAddress();
        if (!$address || !$address->getCountryId()) {
            $address = $quote->getShippingAddress();
            if (!$address->getCountryId()) {
                $address = $quote->getBillingAddress();
            }
        }

        $baseGrandTotal = (float) $total->getBaseGrandTotal();
        if ($baseGrandTotal <= 0.0001 && $address) {
            $baseGrandTotal = (float) $address->getBaseGrandTotal();
        }
        if ($baseGrandTotal <= 0.0001) {
            $baseGrandTotal = (float) $total->getData('base_subtotal_with_discount')
                + (float) $total->getData('base_shipping_amount')
                + (float) $total->getData('base_tax_amount');
        }
        if ($baseGrandTotal <= 0.0001) {
            return $this;
        }

        $customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : 0;
        $email = strtolower(trim((string) $quote->getCustomerEmail()));
        $email = $email !== '' ? $email : null;
        $walletBalance = $this->walletLoader->getWalletBalance($customerId, $email);
        $baseToApply = min($requestedBase, $walletBalance, $baseGrandTotal);
        if ($baseToApply <= 0.0001) {
            // Nothing available; clear request so UI reflects reality.
            $quote->setData('venbhas_giftcard_amount', null);
            $quote->setData('base_venbhas_giftcard_amount', null);
            return $this;
        }

        $total->addTotalAmount(self::CODE, -$baseToApply);
        $total->addBaseTotalAmount(self::CODE, -$baseToApply);
        $total->setGrandTotal((float) $total->getGrandTotal() - $baseToApply);
        $total->setBaseGrandTotal((float) $total->getBaseGrandTotal() - $baseToApply);

        $total->setData('venbhas_giftcard_amount', $baseToApply);
        $total->setData('base_venbhas_giftcard_amount', $baseToApply);
        $quote->setData('venbhas_giftcard_amount', $baseToApply);
        $quote->setData('base_venbhas_giftcard_amount', $baseToApply);

        return $this;
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
