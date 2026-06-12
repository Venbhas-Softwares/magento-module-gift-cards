<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Email;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\GiftCardCode;

/**
 * Sends gift card email to the recipient.
 */
class GiftCardSender
{
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * Initialize sender.
     *
     * @param TransportBuilder $transportBuilder Transport builder
     * @param StoreManagerInterface $storeManager Store manager
     * @param LoggerInterface $logger Logger
     * @param PriceCurrencyInterface $priceCurrency Price currency formatter
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Send gift card email for a gift card code entity.
     *
     * @param GiftCardCode $giftCard Gift card code
     *
     * @return void
     */
    public function send(GiftCardCode $giftCard): void
    {
        // Physical shipment: do not email the gift card code (customer receives physical card).
        $deliveryType = strtolower(trim((string) $giftCard->getData('delivery_type')));
        if ($deliveryType === Config::GIFT_DELIVERY_PHYSICAL || $deliveryType === 'physical') {
            return;
        }

        $toEmail = (string) $giftCard->getData('recipient_email');
        if ($toEmail === '') {
            return;
        }

        $storeId = (int) $giftCard->getData('store_id') ?: (int) $this->storeManager->getStore()->getId();
        $amount = (float) ($giftCard->getData('amount') ?? 0);
        $currency = '';
        $formattedValue = $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            null,
            $currency !== '' ? $currency : null
        );

        $transport = $this->transportBuilder
            ->setTemplateIdentifier('venbhas_giftcard_email_template')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars([
                'giftcard_code' => (string) $giftCard->getData('code'),
                'giftcard_value' => (string) $giftCard->getData('amount'),
                'giftcard_value_formatted' => $formattedValue,
                'giftcard_currency' => '',
                'sender_name' => (string) $giftCard->getData('sender_name'),
                'recipient_name' => (string) $giftCard->getData('recipient_name'),
                'message' => (string) $giftCard->getData('message'),
            ])
            ->setFromByScope('general', $storeId)
            ->addTo($toEmail, (string) $giftCard->getData('recipient_name'))
            ->getTransport();

        try {
            $transport->sendMessage();
        } catch (MailException $e) {
            $this->logger->error(
                'Venbhas GiftCard: could not email gift card after invoice (invoice still saved).',
                [
                    'recipient' => $toEmail,
                    'gift_card_id' => $giftCard->getId(),
                    'exception' => $e,
                ]
            );
        }
    }
}
