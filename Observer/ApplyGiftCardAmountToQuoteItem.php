<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

class ApplyGiftCardAmountToQuoteItem implements ObserverInterface
{
    public function __construct(
        private readonly RequestInterface $request
    ) {}

    public function execute(Observer $observer): void
    {
        /** @var QuoteItem|null $quoteItem */
        $quoteItem = $observer->getData('quote_item');
        $product = $observer->getData('product');
        if (!$quoteItem || !$product || (string)$product->getTypeId() !== GiftCard::TYPE_CODE) {
            return;
        }

        $data = (array)$this->request->getParam('venbhas_giftcard', []);
        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        if ($amount <= 0.0001) {
            return;
        }

        $store = $quoteItem->getQuote()->getStore();
        $baseAmount = (float)$store->getCurrentCurrency()->convert(
            $amount,
            $store->getBaseCurrencyCode()
        );

        $quoteItem->setCustomPrice($baseAmount);
        $quoteItem->setOriginalCustomPrice($baseAmount);
        $quoteItem->getProduct()->setIsSuperMode(true);
    }
}
