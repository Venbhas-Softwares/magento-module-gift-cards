<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Render customer as a clickable link to admin customer edit.
 *
 * - Uses customer_id when present.
 * - Label prefers `customer_name`, falls back to `customer_email`, then customer_id.
 */
class AdminCustomerLink extends Column
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
     * Render the customer name as an admin edit link.
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
            $customerId = isset($item['customer_id']) ? (int) $item['customer_id'] : 0;
            if ($customerId <= 0) {
                continue;
            }

            $label = '';
            if (isset($item['customer_name']) && (string) $item['customer_name'] !== '') {
                $label = (string) $item['customer_name'];
            } elseif (isset($item['customer_email']) && (string) $item['customer_email'] !== '') {
                $label = (string) $item['customer_email'];
            } else {
                $label = (string) $customerId;
            }

            $url = $this->urlBuilder->getUrl('customer/index/edit', ['id' => $customerId]);
            $item[$name] = '<a href="' . $this->escaper->escapeHtmlAttr($url) . '">'
                . $this->escaper->escapeHtml($label)
                . '</a>';
        }

        return $dataSource;
    }
}
