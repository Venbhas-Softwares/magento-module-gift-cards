<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\Component\Listing\Column;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Render numeric amount with store currency for admin grids.
 */
class AdminPrice extends Column
{
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context UI context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory UI factory
     * @param PriceCurrencyInterface $priceCurrency Price formatter
     * @param StoreManagerInterface $storeManager Store manager
     * @param array $components Components
     * @param array $data Component data
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
     * Format grid amount values with currency.
     *
     * @param array $dataSource Listing data
     *
     * @return array
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

            $item[$name] = $formatted;
        }

        return $dataSource;
    }
}
