<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Quote;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Venbhas\GiftCard\Model\GiftCardCode;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardCode\CollectionFactory as CodeCollectionFactory;

class GiftCardRedeemValidator
{
    /**
     * @var CodeCollectionFactory
     */
    private $codeCollectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(
        CodeCollectionFactory $codeCollectionFactory,
        ResourceConnection $resource
    ) {
        $this->codeCollectionFactory = $codeCollectionFactory;
        $this->resource = $resource;
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
        $available = (float) ($gc->getData('balance_amount') ?? 0);
        
        if ($available <= 0.0001) {
            throw new LocalizedException(__('Gift card has no remaining balance.'));
        }
        $this->assertQuoteMatchesRedeemerLock($quote, $gc);

        // Lock the card to the first user who applies it (guest email or logged-in customer),
        // so other customers cannot apply the same code later.
        $this->lockCardToQuoteIfNeeded($quote, $gc);
    }

    /**
     * Whether quote may use this card (active balance and lock rules).
     */
    public function canQuoteUseGiftCard(Quote $quote, GiftCardCode $gc): bool
    {
        if ((int) $gc->getData('status') !== GiftCardCode::STATUS_ACTIVE) {
            return false;
        }
        $available = (float) ($gc->getData('balance_amount') ?? 0);
        if ($available <= 0.0001) {
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

    private function lockCardToQuoteIfNeeded(Quote $quote, GiftCardCode $gc): void
    {
        $hasLock = (int) $gc->getData('redeemer_customer_id') > 0
            || trim((string) $gc->getData('redeemer_email')) !== '';
        if ($hasLock) {
            return;
        }

        $customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;
        $email = strtolower(trim((string) $quote->getCustomerEmail()));
        if (!$customerId && $email === '') {
            return;
        }

        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName('venbhas_giftcard_code');
        $entityId = (int) $gc->getId();
        if ($entityId < 1) {
            return;
        }

        $update = [];
        if ($customerId) {
            $update['redeemer_customer_id'] = $customerId;
        } else {
            $update['redeemer_email'] = $email;
        }

        // Only set the lock if it is still empty in DB (avoid overwriting in races).
        $conn->update(
            $table,
            $update,
            [
                'entity_id = ?' => $entityId,
                '(redeemer_customer_id IS NULL OR redeemer_customer_id = 0)',
                "(redeemer_email IS NULL OR redeemer_email = '')",
            ]
        );
    }
}
