<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Render order_id / increment_id as a clickable link to admin order view.
 *
 * Works for both code and transaction grids:
 * - if `order_id` exists in row -> link to sales/order/view?order_id=...
 * - label prefers `increment_id` if present, otherwise `order_id`
 */
class AdminOrderLink extends Column
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = (string) $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $orderId = isset($item['order_id']) ? (int) $item['order_id'] : 0;
            if ($orderId <= 0) {
                continue;
            }

            $label = '';
            if (isset($item['increment_id']) && (string) $item['increment_id'] !== '') {
                $label = (string) $item['increment_id'];
            } elseif (isset($item[$name]) && (string) $item[$name] !== '') {
                $label = (string) $item[$name];
            } else {
                $label = (string) $orderId;
            }

            $url = $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $orderId]);
            $item[$name] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' .
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return $dataSource;
    }
}

