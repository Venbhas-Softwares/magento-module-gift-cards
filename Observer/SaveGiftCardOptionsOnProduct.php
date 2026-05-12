<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * When "Use Config Settings" is checked on a gift card product, the disabled
 * field value is excluded from POST data. This observer explicitly resets
 * those attributes to empty string (the "use config" sentinel) before save.
 */
class SaveGiftCardOptionsOnProduct implements ObserverInterface
{
    private const FIELDS = [
        GiftOptionsResolver::ATTR_DELIVERY_TYPE,
        GiftOptionsResolver::ATTR_ALLOW_CUSTOM_AMOUNT,
        GiftOptionsResolver::ATTR_ALLOW_CUSTOM_MESSAGE,
        GiftOptionsResolver::ATTR_AMOUNTS_AVAILABLE,
    ];

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getDataObject();

        if (!$product || $product->getTypeId() !== GiftCard::TYPE_CODE) {
            return;
        }

        foreach (self::FIELDS as $field) {
            $useConfigKey = 'use_config_' . $field;
            if ($product->getData($useConfigKey)) {
                $product->setData($field, '');
            }
        }
    }
}
