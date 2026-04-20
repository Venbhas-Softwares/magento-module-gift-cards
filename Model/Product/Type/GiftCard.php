<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Product\Type;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Type;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Psr\Log\LoggerInterface;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;

/**
 * Gift card product type.
 */
class GiftCard extends \Magento\Catalog\Model\Product\Type\Simple
{
    public const TYPE_CODE = 'giftcard';

    /**
     * @var GiftOptionsResolver|null
     */
    private ?GiftOptionsResolver $giftOptionsResolver = null;

    /**
     * Initialize product type.
     *
     * @param Option $catalogProductOption Product option instance
     * @param EavConfig $eavConfig EAV config
     * @param Type $catalogProductType Product type instance
     * @param ManagerInterface $eventManager Event manager
     * @param Database $fileStorageDb File storage DB helper
     * @param Filesystem $filesystem Filesystem
     * @param Registry $coreRegistry Core registry
     * @param LoggerInterface $logger Logger
     * @param ProductRepositoryInterface $productRepository Product repository
     * @param Json|null $serializer Serializer
     * @param UploaderFactory|null $uploaderFactory Uploader factory
     * @param GiftOptionsResolver|null $giftOptionsResolver Gift options resolver
     */
    public function __construct(
        Option $catalogProductOption,
        EavConfig $eavConfig,
        Type $catalogProductType,
        ManagerInterface $eventManager,
        Database $fileStorageDb,
        Filesystem $filesystem,
        Registry $coreRegistry,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        ?Json $serializer = null,
        ?UploaderFactory $uploaderFactory = null,
        ?GiftOptionsResolver $giftOptionsResolver = null
    ) {
        parent::__construct(
            $catalogProductOption,
            $eavConfig,
            $catalogProductType,
            $eventManager,
            $fileStorageDb,
            $filesystem,
            $coreRegistry,
            $logger,
            $productRepository,
            $serializer,
            $uploaderFactory
        );
        $this->giftOptionsResolver = $giftOptionsResolver;
    }

    /**
     * Get product type code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return self::TYPE_CODE;
    }

    /**
     * Physical delivery (product or store scope) makes the line item shippable.
     *
     * @param mixed $product Product
     *
     * @return bool
     */
    public function isVirtual($product)
    {
        if ($this->giftOptionsResolver === null) {
            return true;
        }
        $storeId = (int) $product->getStoreId();
        if ($this->giftOptionsResolver->isPhysicalDeliveryOnly($product, $storeId)) {
            return false;
        }
        return true;
    }

    /**
     * Gift card products always have configurable options.
     *
     * @param mixed $product
     *
     * @return bool
     */
    public function canConfigure($product)
    {
        return true;
    }
}
