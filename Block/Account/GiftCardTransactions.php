<?php

declare(strict_types=1);

/**
 * Gift card transactions block (customer account).
 *
 * @license   https://opensource.org/licenses/osl-3.0.php  OSL-3.0
 * @link      https://venbhas.com
 */
namespace Venbhas\GiftCard\Block\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Phrase;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\CustomerGiftCardTransactionsLoader;

/**
 * Customer account block for gift card transactions history.
 *
 * @license   https://opensource.org/licenses/osl-3.0.php  OSL-3.0
 * @link      https://venbhas.com
 */
class GiftCardTransactions extends Template
{
    /**
     * Customer session model.
     *
     * @var Session
     */
    private Session $_customerSession;

    /**
     * Loader for customer gift card transactions.
     *
     * @var CustomerGiftCardTransactionsLoader
     */
    private CustomerGiftCardTransactionsLoader $_transactionsLoader;

    /**
     * Price formatting service.
     *
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $_priceCurrency;

    /**
     * @var FormKey
     */
    private FormKey $formKey;

    /**
     * Initialize block.
     *
     * @param Context $context Block context
     * @param Session $customerSession Customer session
     * @param CustomerGiftCardTransactionsLoader $transactionsLoader Transactions loader
     * @param PriceCurrencyInterface $priceCurrency Price currency formatter
     * @param array $data Additional data
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        CustomerGiftCardTransactionsLoader $transactionsLoader,
        PriceCurrencyInterface $priceCurrency,
        FormKey $formKey,
        array $data = []
    ) {
        $this->_customerSession = $customerSession;
        $this->_transactionsLoader = $transactionsLoader;
        $this->_priceCurrency = $priceCurrency;
        $this->formKey = $formKey;

        parent::__construct($context, $data);
    }

    /**
     * Get gift card transactions for the current customer.
     *
     * @return GiftCardTransaction[]
     */
    public function getTransactions(): array
    {
        $cid = (int) $this->_customerSession->getCustomerId();
        if ($cid <= 0) {
            return [];
        }

        $email = '';
        try {
            $customerData = method_exists($this->_customerSession, 'getCustomerData')
                ? $this->_customerSession->getCustomerData()
                : null;
            if ($customerData && $customerData->getEmail()) {
                $email = trim((string) $customerData->getEmail());
            }
            if ($email === '') {
                $customer = $this->_customerSession->getCustomer();
                if ($customer && $customer->getEmail()) {
                    $email = trim((string) $customer->getEmail());
                }
            }
        } catch (\Exception $e) {
            $email = '';
        }

        $collection = $this->_transactionsLoader->createCollection(
            $cid,
            $email !== '' ? $email : null,
            100
        );

        return $collection->getItems();
    }

    /**
     * Wallet balance for the current customer (credits - usage).
     */
    public function getWalletBalance(): float
    {
        $cid = (int) $this->_customerSession->getCustomerId();
        if ($cid <= 0) {
            return 0.0;
        }

        $email = '';
        try {
            $customerData = method_exists($this->_customerSession, 'getCustomerData')
                ? $this->_customerSession->getCustomerData()
                : null;
            if ($customerData && $customerData->getEmail()) {
                $email = trim((string) $customerData->getEmail());
            }
            if ($email === '') {
                $customer = $this->_customerSession->getCustomer();
                if ($customer && $customer->getEmail()) {
                    $email = trim((string) $customer->getEmail());
                }
            }
        } catch (\Throwable $e) {
            $email = '';
        }

        return $this->_transactionsLoader->getWalletBalance($cid, $email !== '' ? $email : null);
    }

    /**
     * Signed amount for display (credit: +, usage: -).
     */
    public function formatSignedAmount(GiftCardTransaction $trx): string
    {
        $type = (string) $trx->getData('transaction_type');
        $amount = (float) $trx->getData('amount');
        if ($amount <= 0.0001) {
            return $this->formatAmount(0.0, null);
        }

        $sign = '+';
        if ($type === GiftCardTransaction::ACTION_CHECKOUT_APPLY || $type === GiftCardTransaction::ACTION_REDEEM) {
            $sign = '-';
        }

        return $sign . $this->formatAmount($amount, null);
    }

    /**
     * Get the display label for a transaction action.
     *
     * @param GiftCardTransaction $trx Transaction entity
     *
     * @return Phrase
     */
    public function getActionLabel(GiftCardTransaction $trx): Phrase
    {
        $type = (string) $trx->getData('transaction_type');
        if ($type === GiftCardTransaction::ACTION_CHECKOUT_APPLY) {
            return __('Applied at checkout');
        }
        if ($type === GiftCardTransaction::ACTION_REDEEM) {
            return __('Redeemed');
        }
        if ($type === GiftCardTransaction::ACTION_CREDIT) {
            return __('Credit');
        }

        return __('Gift card');
    }

    /**
     * Format an amount using the store currency settings.
     *
     * @param float       $amount       Amount
     * @param string|null $currencyCode Currency code
     *
     * @return string
     */
    public function formatAmount(float $amount, ?string $currencyCode): string
    {
        return $this->_priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            null,
            $currencyCode
        );
    }

    /**
     * Get the customer order view URL for the given order id.
     *
     * @param int $orderId Order ID
     *
     * @return string
     */
    public function getOrderViewUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }

    public function getAddGiftcardUrl(): string
    {
        return $this->getUrl('venbhas_giftcard/account/addGiftcard');
    }

    public function getFormKeyValue(): string
    {
        return (string) $this->formKey->getFormKey();
    }
}
