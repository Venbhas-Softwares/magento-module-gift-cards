<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Product\GiftOptionsResolver;

/**
 * Observer to validate gift card product fields on add-to-cart.
 */
class ValidateGiftCardFieldsOnAddToCart implements ObserverInterface
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $_request;

    /**
     * @var Config
     */
    private Config $_config;

    /**
     * @var GiftOptionsResolver
     */
    private GiftOptionsResolver $_giftOptionsResolver;

    /**
     * Initialize observer.
     *
     * @param RequestInterface $request Request
     * @param Config $config Module config
     * @param GiftOptionsResolver $giftOptionsResolver Gift options resolver
     */
    public function __construct(
        RequestInterface $request,
        Config $config,
        GiftOptionsResolver $giftOptionsResolver
    ) {
        $this->_request = $request;
        $this->_config = $config;
        $this->_giftOptionsResolver = $giftOptionsResolver;
    }

    /**
     * Execute observer.
     *
     * @param Observer $observer Observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $product = $observer->getData('product');
        if (!$product) {
            return;
        }

        if ((string) $product->getTypeId() !== \Venbhas\GiftCard\Model\Product\Type\GiftCard::TYPE_CODE) {
            return;
        }

        $storeId = (int) $product->getStoreId();
        if (!$this->_config->isEnabled($storeId)) {
            return;
        }

        $data = (array) $this->_request->getParam('venbhas_giftcard', []);

        $required = [
            'recipient_name' => __('Recipient Name'),
            'recipient_email' => __('Recipient Email'),
            'sender_name' => __('Sender Name'),
        ];

        foreach ($required as $key => $label) {
            $val = trim((string) ($data[$key] ?? ''));
            if ($val === '') {
                throw new LocalizedException(__('Please enter %1.', $label));
            }
        }

        if ($this->_giftOptionsResolver->isCustomMessageAllowed($product, $storeId)) {
            $val = trim((string) ($data['message'] ?? ''));
            if ($val === '') {
                throw new LocalizedException(__('Please enter %1.', __('Message')));
            }
        }

        $amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
        if ($amount <= 0.0001) {
            throw new LocalizedException(__('Please select or enter a gift card amount.'));
        }

        $min = $this->_giftOptionsResolver->getMinAmount($product, $storeId);
        $max = $this->_giftOptionsResolver->getMaxAmount($product, $storeId);
        if ($amount < $min || $amount > $max) {
            throw new LocalizedException(
                __('Gift card amount must be between %1 and %2.', $min, $max)
            );
        }

        if (!$this->_giftOptionsResolver->isCustomAmountAllowed($product, $storeId)) {
            $presets = $this->_giftOptionsResolver->getAmountPresets($product, $storeId);
            $match = false;
            foreach ($presets as $p) {
                if (abs($amount - $p) < 0.001) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                throw new LocalizedException(__('Please choose one of the preset gift card amounts.'));
            }
        }

        $deliveryType = $this->_giftOptionsResolver->getDeliveryType($product, $storeId);
        $postedDelivery = isset($data['delivery_type']) ? (string) $data['delivery_type'] : '';
        if ($deliveryType === \Venbhas\GiftCard\Model\Config::GIFT_DELIVERY_BOTH) {
            if ($postedDelivery !== \Venbhas\GiftCard\Model\Config::GIFT_DELIVERY_VIRTUAL
                && $postedDelivery !== \Venbhas\GiftCard\Model\Config::GIFT_DELIVERY_PHYSICAL) {
                throw new LocalizedException(__('Please choose email delivery or physical delivery.'));
            }
        }

        if ($this->_giftOptionsResolver->isPhysicalDeliverySelected($data, $product, $storeId)) {
            $addressFields = [
                'delivery_street' => __('Street address'),
                'delivery_city' => __('City'),
                'delivery_region' => __('State / Province'),
                'delivery_postcode' => __('ZIP / Postal code'),
                'delivery_country' => __('Country'),
            ];
            foreach ($addressFields as $key => $label) {
                $val = trim((string) ($data[$key] ?? ''));
                if ($val === '') {
                    throw new LocalizedException(__('Please enter %1 for physical delivery.', $label));
                }
            }
        }
    }
}
