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
use Venbhas\GiftCard\Model\CustomerGiftCardTransactionsLoader;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\Collection;

/**
 * Customer account block for gift card transactions history.
 *
 * @license   https://opensource.org/licenses/osl-3.0.php  OSL-3.0
 * @link      https://venbhas.com
 */
class GiftCardTransactions extends Template
{
    private const DEFAULT_PAGE_SIZE = 10;

    /** @var array<int, int> */
    private const PAGE_LIMITS = [10 => 10, 20 => 20, 50 => 50];

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
     * @var Collection|null
     */
    private ?Collection $transactionsCollection = null;

    /**
     * Initialize block.
     *
     * @param Context $context Block context
     * @param Session $customerSession Customer session
     * @param CustomerGiftCardTransactionsLoader $transactionsLoader Transactions loader
     * @param PriceCurrencyInterface $priceCurrency Price currency formatter
     * @param FormKey $formKey Form key service
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
     * Get pager HTML for the transactions table.
     *
     * @return string
     */
    public function getPagerHtml(): string
    {
        if ($this->getTransactionsCollection()->getSize() <= 0) {
            return '';
        }

        return (string) $this->getLayout()
            ->createBlock(Template::class)
            ->setTemplate('Venbhas_GiftCard::account/transactions-pager.phtml')
            ->setData('account_block', $this)
            ->toHtml();
    }

    /**
     * Get page size for the transactions grid.
     *
     * @return int
     */
    public function getPageSize(): int
    {
        return $this->resolvePageLimit();
    }

    /**
     * Get allowed page size options for the transactions pager.
     *
     * @return int[]
     */
    public function getAvailablePageLimits(): array
    {
        return array_values(self::PAGE_LIMITS);
    }

    /**
     * Whether multiple page-size options are available.
     *
     * @return bool
     */
    public function showPagerLimitOptions(): bool
    {
        return count(self::PAGE_LIMITS) > 1;
    }

    /**
     * First item number on the current page.
     *
     * @return int
     */
    public function getPagerFirstNum(): int
    {
        $collection = $this->getTransactionsCollection();

        return (int) ($collection->getPageSize() * ($collection->getCurPage() - 1) + 1);
    }

    /**
     * Last item number on the current page.
     *
     * @return int
     */
    public function getPagerLastNum(): int
    {
        $collection = $this->getTransactionsCollection();

        return (int) ($collection->getPageSize() * ($collection->getCurPage() - 1) + $collection->count());
    }

    /**
     * Total number of transactions.
     *
     * @return int
     */
    public function getPagerTotalNum(): int
    {
        return (int) $this->getTransactionsCollection()->getSize();
    }

    /**
     * Last page number.
     *
     * @return int
     */
    public function getPagerLastPageNum(): int
    {
        return (int) $this->getTransactionsCollection()->getLastPageNumber();
    }

    /**
     * Current page number.
     *
     * @return int
     */
    public function getPagerCurrentPage(): int
    {
        return (int) $this->getTransactionsCollection()->getCurPage();
    }

    /**
     * Page numbers to display in the pager frame.
     *
     * @return int[]
     */
    public function getPagerPages(): array
    {
        $lastPage = $this->getPagerLastPageNum();
        if ($lastPage <= 1) {
            return [1];
        }

        $current = $this->getPagerCurrentPage();
        $frame = 5;
        $half = (int) floor($frame / 2);
        $start = max(1, min($current - $half, $lastPage - $frame + 1));
        $end = min($lastPage, $start + $frame - 1);
        $start = max(1, $end - $frame + 1);

        return range($start, $end);
    }

    /**
     * Whether the current page is the first page.
     *
     * @return bool
     */
    public function isPagerFirstPage(): bool
    {
        return $this->getPagerCurrentPage() <= 1;
    }

    /**
     * Whether the current page is the last page.
     *
     * @return bool
     */
    public function isPagerLastPage(): bool
    {
        return $this->getPagerCurrentPage() >= $this->getPagerLastPageNum();
    }

    /**
     * URL for a specific pager page.
     *
     * @param int $page
     * @return string
     */
    public function getPagerPageUrl(int $page): string
    {
        return $this->buildPagerUrl([
            'p' => $page > 1 ? $page : null,
        ]);
    }

    /**
     * URL for the previous pager page.
     *
     * @return string
     */
    public function getPagerPreviousPageUrl(): string
    {
        return $this->getPagerPageUrl(max(1, $this->getPagerCurrentPage() - 1));
    }

    /**
     * URL for the next pager page.
     *
     * @return string
     */
    public function getPagerNextPageUrl(): string
    {
        return $this->getPagerPageUrl(
            min($this->getPagerLastPageNum(), $this->getPagerCurrentPage() + 1)
        );
    }

    /**
     * URL for changing the page size limit.
     *
     * @param int $limit
     * @return string
     */
    public function getPagerLimitUrl(int $limit): string
    {
        $params = ['limit' => $limit];
        $availablePages = (int) ceil($this->getPagerTotalNum() / max(1, $limit));
        $currentPage = $this->getPagerCurrentPage();
        if ($currentPage > 1 && $availablePages > 0 && $currentPage > $availablePages) {
            $params['p'] = $availablePages > 1 ? $availablePages : null;
        }

        return $this->buildPagerUrl($params);
    }

    /**
     * Whether the given limit is the active page size.
     *
     * @param int $limit
     * @return bool
     */
    public function isPagerLimitCurrent(int $limit): bool
    {
        return $limit === $this->resolvePageLimit();
    }

    /**
     * Resolve the active page size from request or default.
     *
     * @return int
     */
    private function resolvePageLimit(): int
    {
        $default = max(1, (int) ($this->getData('page_size') ?: self::DEFAULT_PAGE_SIZE));
        $limit = (int) $this->getRequest()->getParam('limit', $default);

        return array_key_exists($limit, self::PAGE_LIMITS) ? $limit : $default;
    }

    /**
     * Resolve the current page number, capped at last page.
     *
     * @param int $lastPage
     * @return int
     */
    private function resolveCurrentPage(int $lastPage): int
    {
        $page = max(1, (int) $this->getRequest()->getParam('p', 1));

        return $lastPage > 0 ? min($page, $lastPage) : $page;
    }

    /**
     * Build a pager URL with the given query parameters.
     *
     * @param array $params Query parameters
     * @return string
     */
    private function buildPagerUrl(array $params): string
    {
        $query = array_filter(
            $params,
            static fn ($value): bool => $value !== null && $value !== ''
        );

        return $this->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true, '_query' => $query]);
    }

    /**
     * Get paginated transaction collection for the current customer.
     *
     * @return Collection
     */
    public function getTransactionsCollection(): Collection
    {
        if ($this->transactionsCollection !== null) {
            return $this->transactionsCollection;
        }

        $cid = (int) $this->_customerSession->getCustomerId();
        $email = $this->resolveCustomerEmail();

        $limit = $this->resolvePageLimit();
        $this->transactionsCollection = $this->_transactionsLoader->createCollection(
            $cid,
            $email !== '' ? $email : null,
            $limit
        );

        $lastPage = max(1, (int) $this->transactionsCollection->getLastPageNumber());
        $this->transactionsCollection->setCurPage($this->resolveCurrentPage($lastPage));

        return $this->transactionsCollection;
    }

    /**
     * Get gift card transactions for the current page.
     *
     * @return GiftCardTransaction[]
     */
    public function getTransactions(): array
    {
        return $this->getTransactionsCollection()->getItems();
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

        $email = $this->resolveCustomerEmail();

        return $this->_transactionsLoader->getWalletBalance($cid, $email !== '' ? $email : null);
    }

    /**
     * Resolve logged-in customer email for ledger lookups.
     */
    private function resolveCustomerEmail(): string
    {
        try {
            $customerData = method_exists($this->_customerSession, 'getCustomerData')
                ? $this->_customerSession->getCustomerData()
                : null;
            if ($customerData && $customerData->getEmail()) {
                return trim((string) $customerData->getEmail());
            }
            $customer = $this->_customerSession->getCustomer();
            if ($customer && $customer->getEmail()) {
                return trim((string) $customer->getEmail());
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
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
        $amount = (float) $trx->getData('amount');
        if ($amount <= 0.0001) {
            return $this->formatAmount(0.0, null);
        }

        $sign = GiftCardTransaction::isDebit(
            $trx->getData('transaction_type'),
            (string) $trx->getData('description')
        ) ? '-' : '+';

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
        $description = (string) $trx->getData('description');

        if (GiftCardTransaction::isDebit($trx->getData('transaction_type'), $description)) {
            if (str_starts_with(mb_strtolower($description), 'debited for')) {
                return __('Debited at checkout');
            }

            return __('Debit');
        }

        if (GiftCardTransaction::isCredit($trx->getData('transaction_type'), $description)) {
            if (str_contains($description, 'Added a new giftcard')) {
                return __('Redeemed');
            }

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

    /**
     * Get the add gift card endpoint URL.
     *
     * @return string
     */
    public function getAddGiftcardUrl(): string
    {
        return $this->getUrl('venbhas_giftcard/account/addGiftcard');
    }

    /**
     * Get the current CSRF form key value.
     *
     * @return string
     */
    public function getFormKeyValue(): string
    {
        return (string) $this->formKey->getFormKey();
    }
}
