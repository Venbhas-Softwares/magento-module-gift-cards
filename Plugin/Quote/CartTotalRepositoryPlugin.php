<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Quote;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Plugin\AbstractPlugin;

/**
 * Ensures gift card wallet totals are collected before cart totals are read.
 */
class CartTotalRepositoryPlugin extends AbstractPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * Initialize plugin.
     *
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param CartRepositoryInterface $quoteRepository Quote repository
     */
    public function __construct(
        ModuleEnabledGuard $moduleEnabledGuard,
        CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct($moduleEnabledGuard);
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Ensure totals are collected so wallet gift card amounts exist on the quote.
     *
     * @param CartTotalRepositoryInterface $subject Subject
     * @param int|string $cartId Cart ID
     *
     * @return array
     */
    public function beforeGet(CartTotalRepositoryInterface $subject, $cartId): array
    {
        $quote = $this->quoteRepository->getActive((int) $cartId);
        if (!$this->isModuleEnabled((int) $quote->getStoreId())) {
            return [$cartId];
        }

        $quote->collectTotals();

        return [$cartId];
    }
}
