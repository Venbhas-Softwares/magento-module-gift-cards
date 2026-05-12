<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Render a text column in sentence case for admin grids.
 */
class AdminSentenceCase extends Column
{
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
            $value = (string) ($item[$name] ?? '');
            $value = str_replace('_', ' ', trim($value));
            if ($value === '') {
                continue;
            }

            $lower = mb_strtolower($value);
            $item[$name] = mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
        }

        return $dataSource;
    }
}
