<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Quote\Item;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Copies gift card storefront options onto the sales order item so Admin → Order shows
 * Amount, recipients, Gift card delivery, etc. Magento often omits additional_options during convert
 * unless read from quote_item_option directly.
 */
class ToOrderItemPlugin
{
    /**
     * @var Json
     */
    private $json;

    /**
     * Initialize plugin.
     *
     * @param Json $json JSON serializer
     */
    public function __construct(Json $json)
    {
        $this->json = $json;
    }

    /**
     * Copy gift card additional options to the converted order item.
     *
     * @param ToOrderItem $subject Subject
     * @param OrderItemInterface $result Order item
     * @param AbstractItem $item Quote item
     * @param mixed[] $data Data
     *
     * @return OrderItemInterface
     */
    public function afterConvert(
        ToOrderItem $subject,
        OrderItemInterface $result,
        AbstractItem $item,
        $data = []
    ): OrderItemInterface {
        if (!$item instanceof QuoteItem) {
            return $result;
        }

        if ($item->getProductType() !== GiftCard::TYPE_CODE) {
            return $result;
        }

        $additional = $this->extractAdditionalOptions($item);
        if ($additional === []) {
            return $result;
        }

        $orderOpts = $result->getProductOptions();
        if (!is_array($orderOpts)) {
            $orderOpts = [];
        }

        $orderOpts['additional_options'] = $additional;
        $result->setProductOptions($orderOpts);

        return $result;
    }

    /**
     * Extract gift card additional options from quote item.
     *
     * @param QuoteItem $item Quote item
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractAdditionalOptions(QuoteItem $item): array
    {
        $opts = $item->getProductOptions();
        if (!empty($opts['additional_options']) && is_array($opts['additional_options'])) {
            return $opts['additional_options'];
        }

        $option = $item->getOptionByCode('additional_options');
        if (!$option || !$option->getValue()) {
            return [];
        }

        try {
            $decoded = $this->json->unserialize((string) $option->getValue());
        } catch (\Exception $e) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
