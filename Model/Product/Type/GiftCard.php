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

class GiftCard extends \Magento\Catalog\Model\Product\Type\Simple
{
    public const TYPE_CODE = 'giftcard';

    /**
     * @var GiftOptionsResolver|null
     */
    private ?GiftOptionsResolver $giftOptionsResolver = null;

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

    public function getCode(): string
    {
        return self::TYPE_CODE;
    }

    /**
     * Physical delivery (product or store scope) makes the line item shippable.
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

    public function hasRequiredOptions($product)
    {
        return parent::hasRequiredOptions($product);
    }

    /**
     * @param mixed $product
     * @return bool
     */
    public function canConfigure($product)
    {
        return true;
    }
}
