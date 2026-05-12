<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Adminhtml\Customer\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Venbhas\GiftCard\Model\GiftCardTransaction;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\Grid\Collection as TransactionGridCollection;

/**
 * Classic admin grid for customer gift card transactions (filters + pagination).
 *
 * This avoids UI listing ajax spinner issues inside customer edit.
 */
class GiftCardTransactionsGrid extends Extended implements TabInterface
{
    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private \Magento\Framework\ObjectManagerInterface $objectManager;

    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var TimezoneInterface
     */
    private TimezoneInterface $localeDate;

    /**
     * @param Context $context
     * @param BackendHelper $backendHelper
     * @param Registry $registry
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        BackendHelper $backendHelper,
        Registry $registry,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        PriceCurrencyInterface $priceCurrency,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->objectManager = $objectManager;
        $this->priceCurrency = $priceCurrency;
        $this->storeManager = $storeManager;
        $this->localeDate = $context->getLocaleDate();
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('venbhas_giftcard_customer_transactions_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        // IMPORTANT: namespace grid params so they don't pollute other tabs (Orders tab uses _current => true).
        // If we use the default "filter" param, it gets carried into the Orders tab URL and can break it.
        $this->setVarNameFilter('venbhas_gc_filter');
        $this->setVarNameSort('venbhas_gc_sort');
        $this->setVarNameDir('venbhas_gc_dir');
        $this->setVarNamePage('venbhas_gc_page');
        $this->setVarNameLimit('venbhas_gc_limit');

        $this->setSaveParametersInSession(false);
    }

    /**
     * @inheritdoc
     */
    public function getGridUrl()
    {
        return $this->getUrl(
            'venbhas_giftcard/customer/giftcardtransactionsgrid',
            [
                '_current' => true,
                'customer_id' => $this->getCustomerId(),
            ]
        );
    }

    /**
     * Retrieve current customer ID from registry or request.
     *
     * @return int
     */
    private function getCustomerId(): int
    {
        $cid = (int) $this->registry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
        if ($cid <= 0) {
            $cid = (int) $this->getRequest()->getParam('id');
        }
        return $cid;
    }

    /**
     * @inheritdoc
     */
    protected function _prepareCollection()
    {
        $cid = $this->getCustomerId();

        /** @var TransactionGridCollection $collection */
        $collection = $this->objectManager->create(TransactionGridCollection::class);
        if ($cid > 0) {
            $collection->getSelect()->where('main_table.customer_id = ?', $cid);
        } else {
            // No customer context; keep empty.
            $collection->getSelect()->where('1=0');
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @inheritdoc
     */
    protected function _prepareColumns()
    {
        $this->addColumn('created_at', [
            'header' => __('Created At'),
            'index' => 'created_at',
            'type' => 'datetime',
            'frame_callback' => [$this, 'renderCreatedAt'],
        ]);

        $this->addColumn('transaction_type', [
            'header' => __('Transaction Type'),
            'index' => 'transaction_type',
            'frame_callback' => [$this, 'renderSentenceCase'],
        ]);

        $this->addColumn('description', [
            'header' => __('Description'),
            'index' => 'description',
            'frame_callback' => [$this, 'renderSentenceCase'],
        ]);

        $this->addColumn('increment_id', [
            'header' => __('Order #'),
            'index' => 'increment_id',
        ]);

        $this->addColumn('giftcard_code', [
            'header' => __('Gift Card Code'),
            'index' => 'giftcard_code',
        ]);

        $this->addColumn('amount', [
            'header' => __('Amount'),
            'index' => 'amount',
            'type' => 'number',
            'frame_callback' => [$this, 'renderSignedAmount'],
        ]);

        $this->addColumn('previous_balance', [
            'header' => __('Previous Balance'),
            'index' => 'previous_balance',
            'type' => 'number',
            'frame_callback' => [$this, 'renderPrice'],
        ]);

        $this->addColumn('current_balance', [
            'header' => __('Current Balance'),
            'index' => 'current_balance',
            'type' => 'number',
            'frame_callback' => [$this, 'renderPrice'],
        ]);

        $this->addColumn('store_id', [
            'header' => __('Store'),
            'index' => 'store_id',
            'type' => 'store',
            'store_all' => true,
            'store_view' => true,
            'sortable' => false,
        ]);

        return parent::_prepareColumns();
    }

    /**
     * Render created_at column value with locale formatting.
     *
     * @param string $value
     * @return string
     */
    public function renderCreatedAt($value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        try {
            $dt = $this->localeDate->date($raw);
            return $this->escapeHtml($dt->format('M j, Y, g:i:s A'));
        } catch (\Throwable $e) {
            return $this->escapeHtml($raw);
        }
    }

    /**
     * Render column value in sentence case.
     *
     * @param string $value
     * @return string
     */
    public function renderSentenceCase($value): string
    {
        $v = str_replace('_', ' ', trim((string) $value));
        if ($v === '') {
            return '';
        }
        $lower = mb_strtolower($v);
        $out = mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
        return $this->escapeHtml($out);
    }

    /**
     * Render price column with store currency.
     *
     * @param mixed $value
     * @param \Magento\Framework\DataObject $row
     * @param \Magento\Backend\Block\Widget\Grid\Column|null $column
     * @param bool $isExport
     * @return string
     */
    public function renderPrice($value, $row, $column = null, $isExport = false): string
    {
        $index = $column && method_exists($column, 'getIndex') ? (string) $column->getIndex() : '';
        $raw = $index !== '' ? (float) $row->getData($index) : (float) $value;
        $storeId = (int) ($row->getData('store_id') ?? 0);
        try {
            $currency = (string) $this->storeManager->getStore($storeId)->getBaseCurrencyCode();
        } catch (\Throwable $e) {
            $currency = null;
        }
        return $this->priceCurrency->format(
            $raw,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $storeId,
            $currency
        );
    }

    /**
     * Render signed amount (+/-) with colored badge.
     *
     * @param mixed $value
     * @param \Magento\Framework\DataObject $row
     * @param \Magento\Backend\Block\Widget\Grid\Column|null $column
     * @param bool $isExport
     * @return string
     */
    public function renderSignedAmount($value, $row, $column = null, $isExport = false): string
    {
        $amount = (float) $row->getData('amount');
        $formatted = $this->renderPrice($amount, $row, $column, $isExport);
        if ($amount <= 0.0001) {
            return $formatted;
        }

        $type = mb_strtolower((string) $row->getData('transaction_type'));
        $sign = '+';
        if ($type === GiftCardTransaction::ACTION_DEBIT
            || $type === GiftCardTransaction::ACTION_CHECKOUT_APPLY
            || $type === GiftCardTransaction::ACTION_REDEEM
        ) {
            $sign = '-';
        }

        $signClass = $sign === '+'
            ? 'venbhas-gc-sign--plus'
            : 'venbhas-gc-sign--minus';

        return '<span class="venbhas-gc-sign ' . $signClass . '">[' . $sign . ']</span>&nbsp;' . $formatted;
    }

    /**
     * @inheritdoc
     */
    public function getTabLabel(): \Magento\Framework\Phrase
    {
        return __('Gift Card Transactions');
    }

    /**
     * @inheritdoc
     */
    public function getTabTitle(): \Magento\Framework\Phrase
    {
        return __('Gift Card Transactions');
    }

    /**
     * @inheritdoc
     */
    public function canShowTab(): bool
    {
        return $this->getCustomerId() > 0;
    }

    /**
     * @inheritdoc
     */
    public function isHidden(): bool
    {
        return !$this->canShowTab();
    }

    /**
     * @inheritdoc
     */
    public function getTabUrl(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getTabClass(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function isAjaxLoaded(): bool
    {
        return false;
    }
}
