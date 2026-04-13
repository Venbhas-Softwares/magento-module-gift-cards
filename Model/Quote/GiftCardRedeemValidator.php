<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Venbhas\GiftCard\Model\GiftCardCode;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as CodeCollectionFactory;

class GiftCardRedeemValidator
{
    public function __construct(
        private readonly CodeCollectionFactory $codeCollectionFactory
    ) {
    }

    /**
     * Validates code for apply; throws on failure.
     */
    public function assertMayApply(Quote $quote, string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new LocalizedException(__('Please enter a gift card code.'));
        }

        $collection = $this->codeCollectionFactory->create();
        $collection->addFieldToFilter('code', $code)->setPageSize(1);
        /** @var GiftCardCode $gc */
        $gc = $collection->getFirstItem();
        if (!$gc->getId()) {
            throw new LocalizedException(__('Gift card code was not found.'));
        }
        if ((int) $gc->getData('status') !== GiftCardCode::STATUS_ACTIVE) {
            throw new LocalizedException(__('Gift card code is not active.'));
        }
        if ((float) $gc->getData('balance') <= 0.0001) {
            throw new LocalizedException(__('Gift card has no remaining balance.'));
        }
        $this->assertQuoteMatchesRedeemerLock($quote, $gc);
    }

    /**
     * Whether quote may use this card (active balance and lock rules).
     */
    public function canQuoteUseGiftCard(Quote $quote, GiftCardCode $gc): bool
    {
        if ((int) $gc->getData('status') !== GiftCardCode::STATUS_ACTIVE) {
            return false;
        }
        if ((float) $gc->getData('balance') <= 0.0001) {
            return false;
        }
        return $this->quoteMatchesRedeemerLock($quote, $gc);
    }

    public function quoteMatchesRedeemerLock(Quote $quote, GiftCardCode $gc): bool
    {
        $lockedCustomerId = (int) $gc->getData('redeemer_customer_id');
        $lockedEmail = strtolower(trim((string) $gc->getData('redeemer_email')));
        if ($lockedCustomerId <= 0 && $lockedEmail === '') {
            return true;
        }
        if ($lockedCustomerId > 0) {
            return (int) $quote->getCustomerId() === $lockedCustomerId;
        }
        $quoteEmail = strtolower(trim((string) $quote->getCustomerEmail()));

        return $quoteEmail !== '' && $quoteEmail === $lockedEmail;
    }

    private function assertQuoteMatchesRedeemerLock(Quote $quote, GiftCardCode $gc): void
    {
        $lockedCustomerId = (int) $gc->getData('redeemer_customer_id');
        $lockedEmail = strtolower(trim((string) $gc->getData('redeemer_email')));
        if ($lockedCustomerId <= 0 && $lockedEmail === '') {
            return;
        }
        if ($lockedCustomerId > 0) {
            if ((int) $quote->getCustomerId() !== $lockedCustomerId) {
                throw new LocalizedException(
                    __('This gift card can only be used by the customer who first applied it.')
                );
            }

            return;
        }
        $quoteEmail = strtolower(trim((string) $quote->getCustomerEmail()));
        if ($quoteEmail === '' || $quoteEmail !== $lockedEmail) {
            throw new LocalizedException(
                __('This gift card can only be used with the email address that first applied it.')
            );
        }
    }
}
