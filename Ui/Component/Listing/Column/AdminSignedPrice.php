<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\Component\Listing\Column;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Venbhas\GiftCard\Model\GiftCardTransaction;

/**
 * Render signed amount (+/-) with store currency for admin grids.
 *
 * Mirrors the customer/admin-customer "Gift Card Transactions" signed display:
 * - credit (0): +amount
 * - debit (1): -amount
 */
class AdminSignedPrice extends Column
{
    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param StoreManagerInterface $storeManager
     * @param array $components
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        PriceCurrencyInterface $priceCurrency,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        $this->priceCurrency = $priceCurrency;
        $this->storeManager = $storeManager;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = (string) $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $raw = isset($item[$name]) ? (float) $item[$name] : 0.0;
            $storeId = isset($item['store_id']) ? (int) $item['store_id'] : null;

            try {
                $currency = $storeId !== null
                    ? (string) $this->storeManager->getStore($storeId)->getBaseCurrencyCode()
                    : null;
            } catch (\Throwable $e) {
                $currency = null;
            }

            $formatted = $this->priceCurrency->format(
                $raw,
                false,
                PriceCurrencyInterface::DEFAULT_PRECISION,
                $storeId,
                $currency
            );

            if ($raw <= 0.0001) {
                $item[$name] = $formatted;
                continue;
            }

            $sign = '+';
            if (GiftCardTransaction::isDebit(
                $item['transaction_type'] ?? null,
                isset($item['description']) ? (string) $item['description'] : null
            )) {
                $sign = '-';
            }

            $signClass = $sign === '+'
                ? 'venbhas-gc-sign--plus'
                : 'venbhas-gc-sign--minus';

            $badge = '<span class="venbhas-gc-sign ' . $signClass . '">[' . $sign . ']</span>';
            $item[$name] = $badge . '&nbsp;' . $formatted;
        }

        return $dataSource;
    }
}
