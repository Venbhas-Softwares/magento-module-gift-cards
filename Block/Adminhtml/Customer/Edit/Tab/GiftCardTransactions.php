<?php

declare(strict_types=1);

/**
 * Gift card transactions tab (admin customer edit).
 *
 * @license   https://opensource.org/licenses/osl-3.0.php  OSL-3.0
 * @link      https://venbhas.com
 */
namespace Venbhas\GiftCard\Block\Adminhtml\Customer\Edit\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\CustomerGiftCardTransactionsLoader;

/**
 * Admin customer edit tab showing gift card transactions.
 *
 * @license   https://opensource.org/licenses/osl-3.0.php  OSL-3.0
 * @link      https://venbhas.com
 */
class GiftCardTransactions extends Template implements TabInterface
{
    /**
     * Registry service.
     *
     * @var Registry
     */
    private Registry $_registry;

    /**
     * Loader for customer gift card transactions.
     *
     * @var CustomerGiftCardTransactionsLoader
     */
    private CustomerGiftCardTransactionsLoader $_transactionsLoader;

    /**
     * Customer entity repository.
     *
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $_customerRepository;

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
     * @param Registry $registry Core registry
     * @param CustomerGiftCardTransactionsLoader $transactionsLoader Transactions loader
     * @param CustomerRepositoryInterface $customerRepository Customer repository
     * @param PriceCurrencyInterface $priceCurrency Price currency formatter
     * @param array $data Additional data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        CustomerGiftCardTransactionsLoader $transactionsLoader,
        CustomerRepositoryInterface $customerRepository,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        $this->_registry = $registry;
        $this->_transactionsLoader = $transactionsLoader;
        $this->_customerRepository = $customerRepository;
        $this->_priceCurrency = $priceCurrency;

        parent::__construct($context, $data);

        $this->setTemplate('Venbhas_GiftCard::customer/giftcard_transactions.phtml');
    }

    /**
     * Get gift card transactions for the customer being edited.
     *
     * @return GiftCardTransaction[]
     */
    public function getTransactions(): array
    {
        $cid = (int) $this->_registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
        if ($cid <= 0) {
            return [];
        }

        try {
            $email = (string) $this->_customerRepository->getById($cid)->getEmail();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return [];
        }

        $collection = $this->_transactionsLoader->createCollection($cid, $email, 200);

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
        $type = (string) $trx->getData('transaction_type');
        if ($type === GiftCardTransaction::ACTION_DEBIT) {
            return __('Debited at checkout');
        }
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
     * Wallet balance for the customer being edited.
     */
    public function getWalletBalance(): float
    {
        $cid = (int) $this->_registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
        if ($cid <= 0) {
            return 0.0;
        }
        try {
            $email = (string) $this->_customerRepository->getById($cid)->getEmail();
        } catch (\Throwable $e) {
            return 0.0;
        }

        return $this->_transactionsLoader->getWalletBalance($cid, $email !== '' ? $email : null);
    }

    /**
     * Signed amount for display (credit: +, usage: -).
     *
     * @param GiftCardTransaction $trx Transaction entity
     *
     * @return string
     */
    public function formatSignedAmount(GiftCardTransaction $trx): string
    {
        $type = (string) $trx->getData('transaction_type');
        $amount = (float) $trx->getData('amount');
        if ($amount <= 0.0001) {
            return $this->formatAmount(0.0, null);
        }

        $sign = '+';
        if ($type === GiftCardTransaction::ACTION_DEBIT
            || $type === GiftCardTransaction::ACTION_CHECKOUT_APPLY
            || $type === GiftCardTransaction::ACTION_REDEEM
        ) {
            $sign = '-';
        }

        return $sign . $this->formatAmount($amount, null);
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
     * Get the label used for the tab.
     *
     * @return \Magento\Framework\Phrase
     */
    public function getTabLabel(): \Magento\Framework\Phrase
    {
        return __('Gift Card Transactions');
    }

    /**
     * Get the title used for the tab.
     *
     * @return \Magento\Framework\Phrase
     */
    public function getTabTitle(): \Magento\Framework\Phrase
    {
        return __('Gift Card Transactions');
    }

    /**
     * Check whether the tab can be shown.
     *
     * @return bool
     */
    public function canShowTab(): bool
    {
        return (int) $this->_registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID) > 0;
    }

    /**
     * Check whether the tab is hidden.
     *
     * @return bool
     */
    public function isHidden(): bool
    {
        return !$this->canShowTab();
    }

    /**
     * Get the URL used for the tab.
     *
     * @return string
     */
    public function getTabUrl(): string
    {
        return '';
    }

    /**
     * Get the CSS class used for the tab.
     *
     * @return string
     */
    public function getTabClass(): string
    {
        return '';
    }

    /**
     * Check whether the tab is loaded via AJAX.
     *
     * @return bool
     */
    public function isAjaxLoaded(): bool
    {
        return false;
    }

    /**
     * Get the admin order view URL for the given order id.
     *
     * @param int $orderId Order ID
     *
     * @return string
     */
    public function getAdminOrderUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }
}
