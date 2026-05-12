<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\DataProvider\Product\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Ui\Component\Form\Element\Checkbox;
use Magento\Ui\Component\Form\Field;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Adds "Use Config Settings" checkboxes to gift card product attributes,
 * mirroring the native Allow Gift Message toggle + checkbox pattern.
 */
class GiftCardOptions extends AbstractModifier
{
    /**
     * Yes/No fields converted to toggle + use_config checkbox (same as GiftMessage).
     */
    private const TOGGLE_FIELDS = [
        GiftOptionsResolver::ATTR_ALLOW_CUSTOM_AMOUNT,
        GiftOptionsResolver::ATTR_ALLOW_CUSTOM_MESSAGE,
    ];

    /**
     * Select/textarea fields that keep their form element but gain a use_config checkbox.
     */
    private const STANDARD_FIELDS = [
        GiftOptionsResolver::ATTR_DELIVERY_TYPE,
        GiftOptionsResolver::ATTR_AMOUNTS_AVAILABLE,
    ];

    /**
     * @var LocatorInterface
     */
    private LocatorInterface $locator;

    /**
     * @var ArrayManager
     */
    private ArrayManager $arrayManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param LocatorInterface $locator
     * @param ArrayManager $arrayManager
     * @param Config $config
     */
    public function __construct(
        LocatorInterface $locator,
        ArrayManager $arrayManager,
        Config $config
    ) {
        $this->locator = $locator;
        $this->arrayManager = $arrayManager;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function modifyData(array $data): array
    {
        $product = $this->locator->getProduct();
        if ($product->getTypeId() !== GiftCard::TYPE_CODE) {
            return $data;
        }

        $modelId = $product->getId();
        $storeId = (int) $product->getStoreId();

        $allFields = array_merge(self::TOGGLE_FIELDS, self::STANDARD_FIELDS);
        foreach ($allFields as $fieldName) {
            $currentValue = $data[$modelId][static::DATA_SOURCE_DEFAULT][$fieldName] ?? null;
            $isUsingConfig = ($currentValue === null || $currentValue === '' || empty($modelId));

            if ($isUsingConfig) {
                $data[$modelId][static::DATA_SOURCE_DEFAULT][$fieldName] =
                    $this->getConfigValue($fieldName, $storeId);
                $data[$modelId][static::DATA_SOURCE_DEFAULT]['use_config_' . $fieldName] = '1';
            }
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function modifyMeta(array $meta): array
    {
        $product = $this->locator->getProduct();
        if ($product->getTypeId() !== GiftCard::TYPE_CODE) {
            return $meta;
        }

        foreach (self::TOGGLE_FIELDS as $fieldName) {
            $meta = $this->customizeToggleField($meta, $fieldName);
        }

        foreach (self::STANDARD_FIELDS as $fieldName) {
            $meta = $this->customizeStandardField($meta, $fieldName);
        }

        return $meta;
    }

    /**
     * Convert a Yes/No field to a toggle with "Use Config Settings" checkbox.
     *
     * Identical to the native Magento_GiftMessage pattern.
     *
     * @param array $meta
     * @param string $fieldName
     * @return array
     */
    private function customizeToggleField(array $meta, string $fieldName): array
    {
        $containerPath = $this->arrayManager->findPath(
            'container_' . $fieldName,
            $meta,
            null,
            'children'
        );
        $fieldPath = $this->arrayManager->findPath($fieldName, $meta, null, 'children');

        if (!$containerPath || !$fieldPath) {
            return $meta;
        }

        $fieldConfig = $this->arrayManager->get($fieldPath, $meta);
        $sortOrder = $fieldConfig['arguments']['data']['config']['sortOrder'] ?? 10;

        $meta = $this->arrayManager->merge(
            $containerPath,
            $meta,
            [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'formElement' => 'container',
                            'componentType' => 'container',
                            'component' => 'Magento_Ui/js/form/components/group',
                            'label' => false,
                            'required' => false,
                            'breakLine' => false,
                            'sortOrder' => $sortOrder,
                            'dataScope' => '',
                        ],
                    ],
                ],
            ]
        );

        $meta = $this->arrayManager->merge(
            $containerPath,
            $meta,
            [
                'children' => [
                    $fieldName => [
                        'arguments' => [
                            'data' => [
                                'config' => [
                                    'dataScope' => $fieldName,
                                    'additionalClasses' => 'admin__field-x-small',
                                    'component' => 'Magento_Ui/js/form/element/single-checkbox-use-config',
                                    'componentType' => Field::NAME,
                                    'prefer' => 'toggle',
                                    'valueMap' => [
                                        'false' => '0',
                                        'true' => '1',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'use_config_' . $fieldName => $this->buildUseConfigCheckbox(
                        $fieldName,
                        '${$.parentName}.' . $fieldName . ':isUseConfig'
                    ),
                ],
            ]
        );

        return $meta;
    }

    /**
     * Add a "Use Config Settings" checkbox to a select or textarea field.
     *
     * The field keeps its original form element; the checkbox toggles disabled state.
     *
     * @param array $meta
     * @param string $fieldName
     * @return array
     */
    private function customizeStandardField(array $meta, string $fieldName): array
    {
        $containerPath = $this->arrayManager->findPath(
            'container_' . $fieldName,
            $meta,
            null,
            'children'
        );
        $fieldPath = $this->arrayManager->findPath($fieldName, $meta, null, 'children');

        if (!$containerPath || !$fieldPath) {
            return $meta;
        }

        $fieldConfig = $this->arrayManager->get($fieldPath, $meta);
        $sortOrder = $fieldConfig['arguments']['data']['config']['sortOrder'] ?? 10;

        $meta = $this->arrayManager->merge(
            $containerPath,
            $meta,
            [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'formElement' => 'container',
                            'componentType' => 'container',
                            'component' => 'Magento_Ui/js/form/components/group',
                            'label' => false,
                            'required' => false,
                            'breakLine' => false,
                            'sortOrder' => $sortOrder,
                            'dataScope' => '',
                        ],
                    ],
                ],
            ]
        );

        $meta = $this->arrayManager->merge(
            $containerPath,
            $meta,
            [
                'children' => [
                    $fieldName => [
                        'arguments' => [
                            'data' => [
                                'config' => [
                                    'dataScope' => $fieldName,
                                ],
                            ],
                        ],
                    ],
                    'use_config_' . $fieldName => $this->buildUseConfigCheckbox(
                        $fieldName,
                        '${$.parentName}.' . $fieldName . ':disabled'
                    ),
                ],
            ]
        );

        if ($fieldName === GiftOptionsResolver::ATTR_DELIVERY_TYPE) {
            $optionsPath = $fieldPath . '/arguments/data/config/options';
            $meta = $this->arrayManager->set(
                $optionsPath,
                $meta,
                [
                    [
                        'value' => Config::GIFT_DELIVERY_VIRTUAL,
                        'label' => __('Virtual (email delivery)'),
                    ],
                    [
                        'value' => Config::GIFT_DELIVERY_PHYSICAL,
                        'label' => __('Physical (shipped / printed card)'),
                    ],
                    [
                        'value' => Config::GIFT_DELIVERY_BOTH,
                        'label' => __('Both (customer chooses on product page)'),
                    ],
                ]
            );
        }

        return $meta;
    }

    /**
     * Build the "Use Config Settings" checkbox configuration.
     *
     * @param string $fieldName
     * @param string $exportTarget
     * @return array
     */
    private function buildUseConfigCheckbox(string $fieldName, string $exportTarget): array
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'dataType' => 'number',
                        'formElement' => Checkbox::NAME,
                        'componentType' => Field::NAME,
                        'description' => __('Use Config Settings'),
                        'dataScope' => 'use_config_' . $fieldName,
                        'valueMap' => [
                            'false' => '0',
                            'true' => '1',
                        ],
                        'exports' => [
                            'checked' => $exportTarget,
                            '__disableTmpl' => ['checked' => false],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Resolve the store configuration default for a given field.
     *
     * @param string $fieldName
     * @param int $storeId
     * @return string
     */
    private function getConfigValue(string $fieldName, int $storeId): string
    {
        switch ($fieldName) {
            case GiftOptionsResolver::ATTR_DELIVERY_TYPE:
                return $this->config->getGiftDeliveryType($storeId);
            case GiftOptionsResolver::ATTR_ALLOW_CUSTOM_AMOUNT:
                return $this->config->isCustomAmountAllowed($storeId) ? '1' : '0';
            case GiftOptionsResolver::ATTR_ALLOW_CUSTOM_MESSAGE:
                return $this->config->isCustomMessageAllowedByDefault($storeId) ? '1' : '0';
            case GiftOptionsResolver::ATTR_AMOUNTS_AVAILABLE:
                return implode(',', $this->config->getAmountPresets($storeId));
            default:
                return '';
        }
    }
}
