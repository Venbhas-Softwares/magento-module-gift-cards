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
        array $data = []
    ) {
        $this->_customerSession = $customerSession;
        $this->_transactionsLoader = $transactionsLoader;
        $this->_priceCurrency = $priceCurrency;

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
     * Get the display label for a transaction action.
     *
     * @param GiftCardTransaction $trx Transaction entity
     *
     * @return Phrase
     */
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
}
