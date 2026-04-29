<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
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

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context UI context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory UI factory
     * @param UrlInterface $urlBuilder URL builder
     * @param Escaper $escaper HTML escaper
     * @param array $components Components
     * @param array $data Component data
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->escaper = $escaper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Render the order increment ID as an admin order link.
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
            $item[$name] = '<a href="' . $this->escaper->escapeHtmlAttr($url) . '">'
                . $this->escaper->escapeHtml($label)
                . '</a>';
        }

        return $dataSource;
    }
}
