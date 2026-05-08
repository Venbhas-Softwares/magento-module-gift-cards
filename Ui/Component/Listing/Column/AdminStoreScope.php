<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Show store scope as three lines (website, store group, store view) for a numeric store_id.
 */
class AdminStoreScope extends Column
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @inheritdoc
     *
     * Use module grid cell template (text + white-space: pre-line); default text tmpl shows "<br />" literally;
     * HTML tmpl is sometimes not merged from XML for custom columns.
     */
    public function prepare()
    {
        parent::prepare();

        $this->setData(
            'config',
            array_replace_recursive(
                (array) $this->getData('config'),
                ['bodyTmpl' => 'Venbhas_GiftCard/grid/cells/store-scope']
            )
        );
    }

    /**
     * @param \Magento\Framework\View\Element\UiComponent\ContextInterface $context UI context
     * @param \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory UI factory
     * @param StoreManagerInterface $storeManager Store manager
     * @param Escaper $escaper HTML escaper
     * @param array $components Child components
     * @param array $data Component config
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->escaper = $escaper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Replace store_id with a human-readable scope label for display.
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
            $storeId = isset($item[$name]) ? (int) $item[$name] : 0;
            $item[$name] = $this->getScopeDisplayHtml($storeId);
        }

        return $dataSource;
    }

    /**
     * Build multiline label (newlines) for website, store group, and store view.
     *
     * @param int $storeId Store view id
     *
     * @return string
     */
    private function getScopeDisplayHtml(int $storeId): string
    {
        if ($storeId < 0) {
            return $this->escaper->escapeHtml((string) $storeId);
        }

        try {
            $store = $this->storeManager->getStore($storeId);
        } catch (\Throwable $e) {
            return $this->escaper->escapeHtml(
                $storeId === 0 ? (string) __('All Store Views') : (string) $storeId
            );
        }

        $website = $store->getWebsite();
        $group = $store->getGroup();

        $parts = array_filter(
            [
                $website ? trim((string) $website->getName()) : '',
                $group ? trim((string) $group->getName()) : '',
                trim((string) $store->getName()),
            ],
            static function (string $part): bool {
                return $part !== '';
            }
        );

        if ($parts === []) {
            return $this->escaper->escapeHtml((string) $storeId);
        }

        $lines = array_map(
            function (string $part): string {
                return $this->escaper->escapeHtml($part);
            },
            $parts
        );

        return implode("\n", $lines);
    }
}
