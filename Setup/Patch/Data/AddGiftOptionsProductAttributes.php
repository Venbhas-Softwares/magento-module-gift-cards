<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Setup\CategorySetup;
use Magento\Catalog\Setup\CategorySetupFactory;
use Venbhas\GiftCard\Model\Config\Source\Product\GiftDeliveryTypeWithConfig;
use Venbhas\GiftCard\Model\Config\Source\Product\NullableYesNo;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Data patch to add gift card product attributes.
 */
class AddGiftOptionsProductAttributes implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $_moduleDataSetup;

    /**
     * @var CategorySetupFactory
     */
    private CategorySetupFactory $_categorySetupFactory;

    /**
     * Initialize patch.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup Module data setup
     * @param CategorySetupFactory $categorySetupFactory Category setup factory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategorySetupFactory $categorySetupFactory
    ) {
        $this->_moduleDataSetup = $moduleDataSetup;
        $this->_categorySetupFactory = $categorySetupFactory;
    }

    /**
     * Get patch dependencies.
     *
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get patch aliases.
     *
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Apply patch.
     *
     * @return void
     */
    public function apply(): void
    {
        /** @var CategorySetup $setup */
        $setup = $this->_categorySetupFactory->create(['setup' => $this->_moduleDataSetup]);
        $entity = Product::ENTITY;
        if ($setup->getAttribute($entity, GiftOptionsResolver::ATTR_DELIVERY_TYPE)) {
            return;
        }

        $attributeSetId = $setup->getDefaultAttributeSetId($entity);

        $setup->addAttributeGroup($entity, $attributeSetId, 'Gift Options', 14);

        $setup->addAttribute(
            $entity,
            GiftOptionsResolver::ATTR_DELIVERY_TYPE,
            [
                'type' => 'varchar',
                'label' => 'Delivery Type',
                'input' => 'select',
                'source' => GiftDeliveryTypeWithConfig::class,
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => GiftCard::TYPE_CODE,
                'group' => 'Gift Options',
                'sort_order' => 10,
                'note' => 'Leave as "Use configuration defaults" to use Stores → Configuration → Gift Card.',
            ]
        );

        $setup->addAttribute(
            $entity,
            GiftOptionsResolver::ATTR_ALLOW_CUSTOM_AMOUNT,
            [
                'type' => 'varchar',
                'label' => 'Custom Amount Allowed',
                'input' => 'select',
                'source' => NullableYesNo::class,
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => GiftCard::TYPE_CODE,
                'group' => 'Gift Options',
                'sort_order' => 20,
                'note' => 'Whether the customer may enter an amount outside presets (within min/max).',
            ]
        );

        $setup->addAttribute(
            $entity,
            GiftOptionsResolver::ATTR_AMOUNTS_AVAILABLE,
            [
                'type' => 'text',
                'label' => 'Amounts Available',
                'input' => 'textarea',
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => GiftCard::TYPE_CODE,
                'group' => 'Gift Options',
                'sort_order' => 30,
                'note' => 'Comma-separated preset amounts for this product (e.g. 25,50,100). '
                    . 'Leave empty to use configuration defaults.',
            ]
        );

        $setup->addAttribute(
            $entity,
            GiftOptionsResolver::ATTR_ALLOW_CUSTOM_MESSAGE,
            [
                'type' => 'varchar',
                'label' => 'Custom Message Allowed',
                'input' => 'select',
                'source' => NullableYesNo::class,
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => GiftCard::TYPE_CODE,
                'group' => 'Gift Options',
                'sort_order' => 40,
                'note' => 'Whether the gift message field is shown. Default follows configuration when empty.',
            ]
        );
    }
}
