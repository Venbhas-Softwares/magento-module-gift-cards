<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class GiftCardManager
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly GiftCardRedeemValidator $giftCardRedeemValidator
    ) {}

    /**
     * @return string[] Uppercased, unique codes
     */
    public function getCodes(CartInterface $quote): array
    {
        $raw = (string)$quote->getData('venbhas_giftcard_codes');
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $codes = [];
        foreach ($parts as $code) {
            $code = strtoupper(trim((string)$code));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }
        return array_keys($codes);
    }

    public function addCode(CartInterface $quote, string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new LocalizedException(__('Please enter a gift card code.'));
        }
        if (!$quote instanceof Quote) {
            throw new LocalizedException(__('Unable to apply gift card to this cart.'));
        }
        $this->giftCardRedeemValidator->assertMayApply($quote, $code);
        $codes = $this->getCodes($quote);
        $codes[] = $code;
        $codes = array_values(array_unique($codes));
        $quote->setData('venbhas_giftcard_codes', implode(',', $codes));
        $quote->setTotalsCollectedFlag(false);
        $this->cartRepository->save($quote);
    }

    public function removeCode(CartInterface $quote, string $code): void
    {
        $code = strtoupper(trim($code));
        $codes = array_values(array_filter(
            $this->getCodes($quote),
            static fn(string $c): bool => $c !== $code
        ));
        $quote->setData('venbhas_giftcard_codes', $codes ? implode(',', $codes) : null);
        $quote->setTotalsCollectedFlag(false);
        $this->cartRepository->save($quote);
    }

    public function clear(CartInterface $quote): void
    {
        $quote->setData('venbhas_giftcard_codes', null);
        $quote->setTotalsCollectedFlag(false);
        $this->cartRepository->save($quote);
    }
}

