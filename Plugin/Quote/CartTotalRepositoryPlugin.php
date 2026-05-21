<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Quote;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsExtensionFactory;
use Magento\Quote\Api\Data\TotalsInterface;

/**
 * Plugin to expose gift card data on cart totals.
 */
class CartTotalRepositoryPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var TotalsExtensionFactory
     */
    private $totalsExtensionFactory;

    /**
     * Initialize plugin.
     *
     * @param CartRepositoryInterface $quoteRepository Quote repository
     * @param TotalsExtensionFactory $totalsExtensionFactory Totals extension factory
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        TotalsExtensionFactory $totalsExtensionFactory
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->totalsExtensionFactory = $totalsExtensionFactory;
    }

    /**
     * Ensure totals are collected so gift card amounts and balance JSON exist on the quote.
     *
     * @param CartTotalRepositoryInterface $subject Subject
     * @param int|string $cartId Cart ID
     *
     * @return array
     */
    public function beforeGet(CartTotalRepositoryInterface $subject, $cartId): array
    {
        $quote = $this->quoteRepository->getActive((int) $cartId);
        $quote->collectTotals();

        return [$cartId];
    }

    /**
     * Expose gift card codes and balance details on totals (checkout reads extension_attributes on root).
     *
     * @param CartTotalRepositoryInterface $subject Subject
     * @param TotalsInterface $result Totals result
     * @param int|string $cartId Cart ID
     *
     * @return TotalsInterface
     */
    public function afterGet(
        CartTotalRepositoryInterface $subject,
        TotalsInterface $result,
        $cartId
    ): TotalsInterface {
        $quote = $this->quoteRepository->getActive((int) $cartId);
        $ext = $result->getExtensionAttributes();
        if ($ext === null) {
            $ext = $this->totalsExtensionFactory->create();
        }
        $ext->setVenbhasGiftcardCodes((string) $quote->getData('venbhas_giftcard_codes'));
        $result->setExtensionAttributes($ext);

        return $result;
    }
}
