<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Observer to apply selected gift card amount as custom price on quote item.
 */
class ApplyGiftCardAmountToQuoteItem extends AbstractObserver
{
    /**
     * @var RequestInterface
     */
    private RequestInterface $_request;

    /**
     * Initialize observer.
     *
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param RequestInterface $request Request
     */
    public function __construct(
        ModuleEnabledGuard $moduleEnabledGuard,
        RequestInterface $request
    ) {
        parent::__construct($moduleEnabledGuard);
        $this->_request = $request;
    }

    /**
     * @inheritdoc
     */
    protected function executeWhenEnabled(Observer $observer): void
    {
        /** @var QuoteItem|null $quoteItem */
        $quoteItem = $observer->getData('quote_item');
        $product = $observer->getData('product');
        if (!$quoteItem || !$product || (string)$product->getTypeId() !== GiftCard::TYPE_CODE) {
            return;
        }

        $data = (array)$this->_request->getParam('venbhas_giftcard', []);
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
