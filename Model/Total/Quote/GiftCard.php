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

class GiftCard extends AbstractTotal
{
    public const CODE = 'venbhas_giftcard';

    public function __construct(
        private readonly GiftCardManager $giftCardManager,
        private readonly CodeCollectionFactory $codeCollectionFactory,
        private readonly Config $config,
        private readonly Json $json,
        private readonly GiftCardRedeemValidator $redeemValidator
    ) {
        $this->setCode(self::CODE);
    }

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        $total->setData('venbhas_giftcard_amount', 0.0);
        $total->setData('base_venbhas_giftcard_amount', 0.0);
        $quote->setData('venbhas_giftcard_amount', 0.0);
        $quote->setData('base_venbhas_giftcard_amount', 0.0);
        $quote->setData('venbhas_giftcard_applied', null);
        $quote->setData('venbhas_giftcard_usage_details', null);
        $quote->setData('venbhas_giftcard_balance_details', null);

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
            $balance = (float) $giftCard->getData('balance');
            if ($balance <= 0.0001) {
                continue;
            }
            $remaining = max(0.0, $baseGrandTotal - $baseToApply);
            if ($remaining <= 0.0001) {
                break;
            }
            $use = min($balance, $remaining);
            $baseToApply += $use;
            $applied[] = ['code' => $code, 'base_amount' => $use];
            $balanceAfter = max(0.0, $balance - $use);
            $detailRows[] = [
                'code' => $code,
                'balance_before' => $balance,
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
        $quote->setData('venbhas_giftcard_usage_details', $appliedJson);

        return $this;
    }

    /**
     * Drop codes that are invalid for this quote (wrong redeemer lock, etc.).
     *
     * @param string[] $codes
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
     * @param string[] $codes
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
            $balance = (float) $giftCard->getData('balance');
            if ($balance <= 0.0001 || (int) $giftCard->getData('status') !== GiftCardCode::STATUS_ACTIVE) {
                continue;
            }
            $rows[] = [
                'code' => $code,
                'balance_before' => $balance,
                'amount_applied' => 0.0,
                'balance_after' => $balance,
                'currency' => (string) $quote->getBaseCurrencyCode(),
            ];
        }
        $quote->setData('venbhas_giftcard_balance_details', $this->json->serialize($rows));
    }

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
