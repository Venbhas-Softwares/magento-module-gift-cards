<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Venbhas\GiftCard\Model\CustomerGiftCardTransactionsLoader;

/**
 * Checkout controller to apply gift amount (wallet) via AJAX.
 */
class Apply implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerGiftCardTransactionsLoader
     */
    private $walletLoader;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * Initialize controller.
     *
     * @param RequestInterface $request Request
     * @param JsonFactory $jsonFactory JSON result factory
     * @param CheckoutSession $checkoutSession Checkout session
     * @param CustomerGiftCardTransactionsLoader $walletLoader Wallet balance loader
     * @param PriceCurrencyInterface $priceCurrency Price currency formatter
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        CustomerGiftCardTransactionsLoader $walletLoader,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->walletLoader = $walletLoader;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Execute action.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            $amount = (float) $this->request->getParam('amount');
            if ($amount <= 0.0001) {
                $payload = $this->readJsonBody();
                $amount = (float) ($payload['amount'] ?? 0);
            }
            if ($amount <= 0.0001) {
                throw new LocalizedException(__('Please enter a gift amount.'));
            }

            $customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : 0;
            $email = strtolower(trim((string) $quote->getCustomerEmail()));
            $email = $email !== '' ? $email : null;

            $available = $this->walletLoader->getWalletBalance($customerId, $email);
            if ($amount > $available + 0.009) {
                throw new LocalizedException(
                    __(
                        'The amount cannot exceed your available gift balance (%1).',
                        $this->priceCurrency->format(
                            $available,
                            false,
                            PriceCurrencyInterface::DEFAULT_PRECISION,
                            (int) $quote->getStoreId()
                        )
                    )
                );
            }

            $maxApplicable = $this->getMaxApplicableBaseAmount($quote);
            if ($maxApplicable > 0.0001 && $amount > $maxApplicable + 0.009) {
                throw new LocalizedException(
                    __(
                        'The amount cannot exceed the order total (%1).',
                        $this->priceCurrency->format(
                            $maxApplicable,
                            false,
                            PriceCurrencyInterface::DEFAULT_PRECISION,
                            (int) $quote->getStoreId()
                        )
                    )
                );
            }

            $baseAmount = $this->resolveBaseAmount($quote, $amount);

            $quote->setData('venbhas_giftcard_amount', $amount);
            $quote->setData('base_venbhas_giftcard_amount', $baseAmount);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();

            $applied = (float) ($quote->getData('base_venbhas_giftcard_amount') ?? 0);
            if ($applied <= 0.0001) {
                $quote->setData('venbhas_giftcard_amount', null);
                $quote->setData('base_venbhas_giftcard_amount', null);
                throw new LocalizedException(
                    __('Unable to apply gift amount. Please check your balance and order total.')
                );
            }

            $quote->save();

            return $result->setData(['success' => true, 'message' => (string)__('Gift amount applied.')]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Order total payable before gift card (base currency).
     *
     * @param Quote $quote Quote
     *
     * @return float
     */
    private function getMaxApplicableBaseAmount(Quote $quote): float
    {
        if (!$quote->getTotalsCollectedFlag()) {
            $quote->collectTotals();
        }

        $address = $quote->getShippingAddress();
        if (!$address->getCountryId()) {
            $address = $quote->getBillingAddress();
        }

        $grandTotal = max(0.0, (float) $address->getBaseGrandTotal());
        $applied = max(0.0, (float) ($quote->getData('base_venbhas_giftcard_amount') ?? 0));

        return $grandTotal + $applied;
    }

    /**
     * Convert a storefront amount to base currency when needed.
     *
     * @param Quote $quote Quote
     * @param float $amount Storefront amount
     *
     * @return float
     */
    private function resolveBaseAmount(Quote $quote, float $amount): float
    {
        $baseCurrency = (string) $quote->getBaseCurrencyCode();
        $quoteCurrency = (string) $quote->getQuoteCurrencyCode();
        if ($baseCurrency === '' || $quoteCurrency === '' || $baseCurrency === $quoteCurrency) {
            return $amount;
        }

        $rate = (float) $quote->getBaseToQuoteRate();
        if ($rate <= 0.0001) {
            return $amount;
        }

        return round($amount / $rate, 4);
    }

    /**
     * Read request JSON body as array.
     *
     * @return array<string,mixed>
     */
    private function readJsonBody(): array
    {
        try {
            $raw = (string)$this->request->getContent();
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
