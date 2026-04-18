<?php

declare(strict_types=1);

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

class GiftCardTransactions extends Template implements TabInterface
{
    protected $_template = 'Venbhas_GiftCard::customer/giftcard_transactions.phtml';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CustomerGiftCardTransactionsLoader $transactionsLoader,
        private readonly CustomerRepositoryInterface $customerRepository,
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
        $cid = (int) $this->registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
        if ($cid <= 0) {
            return [];
        }

        try {
            $email = (string) $this->customerRepository->getById($cid)->getEmail();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return [];
        }

        $collection = $this->transactionsLoader->createCollection($cid, $email, 200);

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

    public function getTabLabel(): \Magento\Framework\Phrase
    {
        return __('Gift Card Transactions');
    }

    public function getTabTitle(): \Magento\Framework\Phrase
    {
        return __('Gift Card Transactions');
    }

    public function canShowTab(): bool
    {
        return (int) $this->registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID) > 0;
    }

    public function isHidden(): bool
    {
        return !$this->canShowTab();
    }

    public function getTabUrl(): string
    {
        return '';
    }

    public function getTabClass(): string
    {
        return '';
    }

    public function isAjaxLoaded(): bool
    {
        return false;
    }

    public function getAdminOrderUrl(int $orderId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $orderId]);
    }
}
