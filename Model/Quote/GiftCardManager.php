<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Manages applying and removing gift card codes on a quote.
 */
class GiftCardManager
{
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $_cartRepository;

    /**
     * @var GiftCardRedeemValidator
     */
    private GiftCardRedeemValidator $_giftCardRedeemValidator;

    /**
     * Initialize manager.
     *
     * @param CartRepositoryInterface $cartRepository Cart repository
     * @param GiftCardRedeemValidator $giftCardRedeemValidator Redeem validator
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        GiftCardRedeemValidator $giftCardRedeemValidator
    ) {
        $this->_cartRepository = $cartRepository;
        $this->_giftCardRedeemValidator = $giftCardRedeemValidator;
    }

    /**
     * Get currently applied gift card codes from a quote.
     *
     * @param CartInterface $quote Quote
     *
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

    /**
     * Apply a gift card code to a quote.
     *
     * @param CartInterface $quote Quote
     * @param string $code Gift card code
     *
     * @return void
     */
    public function addCode(CartInterface $quote, string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new LocalizedException(__('Please enter a gift card code.'));
        }
        if (!$quote instanceof Quote) {
            throw new LocalizedException(__('Unable to apply gift card to this cart.'));
        }
        $this->_giftCardRedeemValidator->assertMayApply($quote, $code);
        $codes = $this->getCodes($quote);
        $codes[] = $code;
        $codes = array_values(array_unique($codes));
        $quote->setData('venbhas_giftcard_codes', implode(',', $codes));
        $quote->setTotalsCollectedFlag(false);
        $this->_cartRepository->save($quote);
    }

    /**
     * Remove a gift card code from a quote.
     *
     * @param CartInterface $quote Quote
     * @param string $code Gift card code
     *
     * @return void
     */
    public function removeCode(CartInterface $quote, string $code): void
    {
        $code = strtoupper(trim($code));
        $codes = array_values(array_filter(
            $this->getCodes($quote),
            static fn(string $c): bool => $c !== $code
        ));
        $quote->setData('venbhas_giftcard_codes', $codes ? implode(',', $codes) : null);
        $quote->setTotalsCollectedFlag(false);
        $this->_cartRepository->save($quote);
    }

    /**
     * Clear all applied gift card codes from a quote.
     *
     * @param CartInterface $quote Quote
     *
     * @return void
     */
    public function clear(CartInterface $quote): void
    {
        $quote->setData('venbhas_giftcard_codes', null);
        $quote->setTotalsCollectedFlag(false);
        $this->_cartRepository->save($quote);
    }
}
