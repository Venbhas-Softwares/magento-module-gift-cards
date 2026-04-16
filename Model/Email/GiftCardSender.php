<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model\Email;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Venbhas\GiftCard\Model\GiftCardCode;

class GiftCardSender
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {}

    public function send(GiftCardCode $giftCard): void
    {
        $toEmail = (string)$giftCard->getData('recipient_email');
        if ($toEmail === '') {
            return;
        }

        $storeId = (int)$giftCard->getData('store_id') ?: (int)$this->storeManager->getStore()->getId();
        $transport = $this->transportBuilder
            ->setTemplateIdentifier('venbhas_giftcard_email_template')
            ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars([
                'giftcard_code' => (string)$giftCard->getData('code'),
                'giftcard_value' => (string)$giftCard->getData('initial_value'),
                'giftcard_currency' => (string)$giftCard->getData('currency_code'),
                'sender_name' => (string)$giftCard->getData('sender_name'),
                'recipient_name' => (string)$giftCard->getData('recipient_name'),
                'message' => (string)$giftCard->getData('message'),
            ])
            ->setFromByScope('general', $storeId)
            ->addTo($toEmail, (string)$giftCard->getData('recipient_name'))
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

