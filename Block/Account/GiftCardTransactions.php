<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Phrase;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\CustomerGiftCardTransactionsLoader;

class GiftCardTransactions extends Template
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly CustomerGiftCardTransactionsLoader $transactionsLoader,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return GiftCardTransaction[]
     */
    public function getTransactions(): array
    {
        $cid = (int) $this->customerSession->getCustomerId();
        if ($cid <= 0) {
            return [];
        }

        $email = '';
        try {
            $customerData = method_exists($this->customerSession, 'getCustomerData')
                ? $this->customerSession->getCustomerData()
                : null;
            if ($customerData && $customerData->getEmail()) {
                $email = trim((string) $customerData->getEmail());
            }
            if ($email === '') {
                $customer = $this->customerSession->getCustomer();
                if ($customer && $customer->getEmail()) {
                    $email = trim((string) $customer->getEmail());
                }
            }
        } catch (\Exception $e) {
            $email = '';
        }

        $collection = $this->transactionsLoader->createCollection(
            $cid,
            $email !== '' ? $email : null,
            100
        );

        return $collection->getItems();
    }

    public function getActionLabel(GiftCardTransaction $trx): Phrase
    {
        $action = (string) $trx->getData('action');
        if ($action === GiftCardTransaction::ACTION_CHECKOUT_APPLY) {
            return __('Applied at checkout');
        }
        if ($action === GiftCardTransaction::ACTION_REDEEM) {
            return ((int) $trx->getData('invoice_id')) > 0
                ? __('Redeemed on invoice payment')
                : __('Redeemed when order was placed');
        }

        return __('Gift card');
    }

    public function formatAmount(float $amount, ?string $currencyCode): string
    {
        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            null,
            $currencyCode
        );
    }

    public function getOrderViewUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }
}
