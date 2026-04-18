<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\GiftCardCode;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\Collection;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as CodeCollectionFactory;

/**
 * Lists gift cards this customer has applied at checkout (locked redeemer), not every purchased/recipient card.
 */
class MyGiftCards extends Template
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CodeCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CodeCollectionFactory $collectionFactory,
        PriceCurrencyInterface $priceCurrency,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->collectionFactory = $collectionFactory;
        $this->priceCurrency = $priceCurrency;
        $this->storeManager = $storeManager;
    }

    /**
     * Gift cards this customer has applied (checkout); matches redeemer lock only.
     */
    public function getGiftCards(): Collection
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $collection = $this->collectionFactory->create();

        if ($customerId <= 0) {
            $collection->addFieldToFilter('entity_id', ['eq' => -1]);

            return $collection;
        }

        $email = '';
        try {
            if (method_exists($this->customerSession, 'getCustomerData')) {
                $customerData = $this->customerSession->getCustomerData();
                if ($customerData && $customerData->getEmail()) {
                    $email = trim((string) $customerData->getEmail());
                }
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

        $conn = $collection->getConnection();
        $cid = (int) $customerId;

        if ($email !== '') {
            $emailNorm = strtolower($email);
            $collection->getSelect()->where(
                '(main_table.redeemer_customer_id = ' . $cid
                . ' OR LOWER(main_table.redeemer_email) = ' . $conn->quote($emailNorm)
                . ')'
            );
        } else {
            $collection->addFieldToFilter('redeemer_customer_id', $customerId);
        }

        $collection->setOrder('created_at', 'DESC');

        return $collection;
    }

    public function getStatusLabel($card): Phrase
    {
        $status = (int) $card->getData('status');

        switch ($status) {
            case GiftCardCode::STATUS_PENDING:
                return __('Pending');

            case GiftCardCode::STATUS_ACTIVE:
                return __('Active');

            case GiftCardCode::STATUS_INACTIVE:
                return __('Used');

            default:
                return __('Unknown');
        }
    }

    public function formatMoney(float $amount, ?string $currencyCode): string
    {
        $currencyCode = ($currencyCode !== null && $currencyCode !== '')
            ? $currencyCode
            : $this->storeManager->getStore()->getCurrentCurrencyCode();

        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            null,
            $currencyCode
        );
    }

    /**
     * Prefer balance_amount; tolerate legacy columns if ever present on old DBs.
     *
     * @param GiftCardCode|\Magento\Framework\DataObject $card
     */
    public function getBalanceValue($card): float
    {
        foreach (['balance_amount', 'amount', 'balance'] as $attr) {
            $v = $card->getData($attr);
            if ($v !== null && $v !== '') {
                return (float) $v;
            }
        }

        return 0.0;
    }

    /**
     * @param GiftCardCode|\Magento\Framework\DataObject $card
     */
    public function getInitialValue($card): float
    {
        $v = $card->getData('initial_value');

        return $v !== null && $v !== '' ? (float) $v : 0.0;
    }
}
