<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Total\Quote;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\GiftCardCode;
use Venbhas\GiftCard\Model\Quote\GiftCardManager;
use Venbhas\GiftCard\Model\Quote\GiftCardRedeemValidator;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as CodeCollectionFactory;

/**
 * Quote total collector for gift card discount.
 */
class GiftCard extends AbstractTotal
{
    public const CODE = 'venbhas_giftcard';

    /**
     * @var GiftCardManager
     */
    private $giftCardManager;

    /**
     * @var CodeCollectionFactory
     */
    private $codeCollectionFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var GiftCardRedeemValidator
     */
    private $redeemValidator;

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
        GiftCardManager $giftCardManager,
        CodeCollectionFactory $codeCollectionFactory,
        Config $config,
        Json $json,
        GiftCardRedeemValidator $redeemValidator
    ) {
        $this->giftCardManager = $giftCardManager;
        $this->codeCollectionFactory = $codeCollectionFactory;
        $this->config = $config;
        $this->json = $json;
        $this->redeemValidator = $redeemValidator;
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

        // Preserve previously computed gift card data. collectTotals() can run multiple times;
        // if a later pass starts with grand total already 0, we must not lose the applied JSON.
        $prevAmount = (float) ($quote->getData('base_venbhas_giftcard_amount') ?? 0);
        $prevApplied = (string) ($quote->getData('venbhas_giftcard_applied') ?? '');

        $total->setData('venbhas_giftcard_amount', 0.0);
        $total->setData('base_venbhas_giftcard_amount', 0.0);
        $quote->setData('venbhas_giftcard_amount', 0.0);
        $quote->setData('base_venbhas_giftcard_amount', 0.0);
        $quote->setData('venbhas_giftcard_applied', null);
        $quote->unsetData('venbhas_giftcard_balance_details');

        if (!$this->config->isEnabled((int) $quote->getStoreId())) {
            return $this;
        }

        $codesOnQuote = $this->giftCardManager->getCodes($quote);
        $codes = $this->filterCodesForQuote($quote, $codesOnQuote);
        if ($codes !== $codesOnQuote) {
            $quote->setData('venbhas_giftcard_codes', $codes ? implode(',', $codes) : null);
        }

        if (!$codes) {
            return $this;
        }

        $baseGrandTotal = (float) $total->getBaseGrandTotal();
        if ($baseGrandTotal <= 0.0001) {
            // Reconstruct the pre-gift-card base when grand total is already zero (e.g. another
            // collector ran first, or a second totals pass) so codes and JSON still persist on quote.
            $baseGrandTotal = (float) $total->getData('base_subtotal_with_discount')
                + (float) $total->getData('base_shipping_amount')
                + (float) $total->getData('base_tax_amount');
        }
        if ($baseGrandTotal <= 0.0001) {
            if ($prevAmount > 0.0001 && $prevApplied !== '') {
                $quote->setData('venbhas_giftcard_amount', $prevAmount);
                $quote->setData('base_venbhas_giftcard_amount', $prevAmount);
                $quote->setData('venbhas_giftcard_applied', $prevApplied);
                $total->setData('venbhas_giftcard_amount', $prevAmount);
                $total->setData('base_venbhas_giftcard_amount', $prevAmount);

                return $this;
            }
            $this->attachBalanceDetailsForDisplay($quote, $codes);

            return $this;
        }

        $collection = $this->codeCollectionFactory->create();
        $collection->addFieldToFilter('code', ['in' => $codes]);
        $collection->addFieldToFilter('status', GiftCardCode::STATUS_ACTIVE);

        $baseToApply = 0.0;
        $applied = [];
        $detailRows = [];

        foreach ($collection as $giftCard) {
            if (!$this->redeemValidator->canQuoteUseGiftCard($quote, $giftCard)) {
                continue;
            }
            $code = strtoupper((string) $giftCard->getData('code'));
            $amount = (float) ($giftCard->getData('balance_amount') ?? 0);
            if ($amount <= 0.0001) {
                // Backward-compat: older installs may use column name "amount" or "balance".
                $amount = (float) ($giftCard->getData('amount') ?? 0);
                if ($amount <= 0.0001) {
                    $amount = (float) ($giftCard->getData('balance') ?? 0);
                }
            }
            if ($amount <= 0.0001) {
                continue;
            }
            $remaining = max(0.0, $baseGrandTotal - $baseToApply);
            if ($remaining <= 0.0001) {
                break;
            }
            $use = min($amount, $remaining);
            $baseToApply += $use;
            $applied[] = ['code' => $code, 'base_amount' => $use];
            $balanceAfter = max(0.0, $amount - $use);
            $detailRows[] = [
                'code' => $code,
                'balance_before' => $amount,
                'amount_applied' => $use,
                'balance_after' => $balanceAfter,
                'currency' => (string) $quote->getBaseCurrencyCode(),
            ];
        }

        if ($detailRows !== []) {
            $quote->setData('venbhas_giftcard_balance_details', $this->json->serialize($detailRows));
        } else {
            $this->attachBalanceDetailsForDisplay($quote, $codes);
        }

        if ($baseToApply <= 0.0001) {
            return $this;
        }

        $toApply = $baseToApply;

        $total->addTotalAmount(self::CODE, -$toApply);
        $total->addBaseTotalAmount(self::CODE, -$baseToApply);
        $total->setGrandTotal((float) $total->getGrandTotal() - $toApply);
        $total->setBaseGrandTotal((float) $total->getBaseGrandTotal() - $baseToApply);

        $total->setData('venbhas_giftcard_amount', $toApply);
        $total->setData('base_venbhas_giftcard_amount', $baseToApply);
        $quote->setData('venbhas_giftcard_amount', $toApply);
        $quote->setData('base_venbhas_giftcard_amount', $baseToApply);
        $appliedJson = $this->json->serialize($applied);
        $quote->setData('venbhas_giftcard_applied', $appliedJson);

        return $this;
    }

    /**
     * Drop codes that are invalid for this quote (wrong redeemer lock, etc.).
     *
     * @param Quote $quote Quote
     * @param string[] $codes
     *
     * @return string[]
     */
    private function filterCodesForQuote(Quote $quote, array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $collection = $this->codeCollectionFactory->create();
        $collection->addFieldToFilter('code', ['in' => $codes]);
        $allowed = [];
        foreach ($collection as $gc) {
            $code = strtoupper((string) $gc->getData('code'));
            if ($this->redeemValidator->canQuoteUseGiftCard($quote, $gc)) {
                $allowed[$code] = true;
            }
        }
        $out = [];
        foreach ($codes as $c) {
            $u = strtoupper(trim($c));
            if (isset($allowed[$u])) {
                $out[] = $u;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Show current balances for applied codes on checkout (even before any amount is used this order).
     *
     * @param Quote $quote Quote
     * @param string[] $codes
     *
     * @return void
     */
    private function attachBalanceDetailsForDisplay(Quote $quote, array $codes): void
    {
        $collection = $this->codeCollectionFactory->create();
        $collection->addFieldToFilter('code', ['in' => $codes]);
        $rows = [];
        foreach ($collection as $giftCard) {
            if (!$this->redeemValidator->canQuoteUseGiftCard($quote, $giftCard)) {
                continue;
            }
            $code = strtoupper((string) $giftCard->getData('code'));
            $amount = (float) ($giftCard->getData('balance_amount') ?? 0);
            if ($amount <= 0.0001) {
                $amount = (float) ($giftCard->getData('amount') ?? 0);
                if ($amount <= 0.0001) {
                    $amount = (float) ($giftCard->getData('balance') ?? 0);
                }
            }
            if ($amount <= 0.0001 || (int) $giftCard->getData('status') !== GiftCardCode::STATUS_ACTIVE) {
                continue;
            }
            $rows[] = [
                'code' => $code,
                'balance_before' => $amount,
                'amount_applied' => 0.0,
                'balance_after' => $amount,
                'currency' => (string) $quote->getBaseCurrencyCode(),
            ];
        }
        $quote->setData('venbhas_giftcard_balance_details', $this->json->serialize($rows));
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
