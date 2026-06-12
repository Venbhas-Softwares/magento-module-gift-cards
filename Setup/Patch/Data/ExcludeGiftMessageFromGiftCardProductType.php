<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Restricts core {@see gift_message_available} to non–gift-card product types.
 *
 * Gift card products use {@see venbhas_gc_allow_custom_message} instead.
 */
class ExcludeGiftMessageFromGiftCardProductType implements DataPatchInterface
{
    private const ATTRIBUTE_CODE = 'gift_message_available';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup Module data setup
     * @param CategorySetupFactory $categorySetupFactory Category setup factory
     * @param Type $productType Product type model
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CategorySetupFactory $categorySetupFactory,
        private readonly Type $productType
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): void
    {
        $setup = $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);
        $entity = Product::ENTITY;

        if (!$setup->getAttributeId($entity, self::ATTRIBUTE_CODE)) {
            return;
        }

        $applyTo = $this->buildApplyToExcludingGiftCard();
        $setup->updateAttribute($entity, self::ATTRIBUTE_CODE, 'apply_to', $applyTo);
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [
            AddGiftOptionsProductAttributes::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Build apply_to attribute value excluding the gift card product type.
     *
     * @return string
     */
    private function buildApplyToExcludingGiftCard(): string
    {
        $typeIds = array_keys($this->productType->getTypes());
        $typeIds = array_values(array_filter(
            $typeIds,
            static fn (string $typeId): bool => $typeId !== GiftCard::TYPE_CODE
        ));

        return implode(',', $typeIds);
    }
}
