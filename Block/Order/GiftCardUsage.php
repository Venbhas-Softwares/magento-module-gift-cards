<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Order;

use Magento\Framework\App\State;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;

class GiftCardUsage extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Json $json,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly State $appState,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('current_order');
        return $order instanceof OrderInterface ? $order : null;
    }

    /**
     * @return array<int, array{code: string, amount: float}>
     */
    public function getUsageRows(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }
        $raw = (string) $order->getData('venbhas_giftcard_usage_details');
        if ($raw === '') {
            $raw = (string) $order->getData('venbhas_giftcard_applied');
        }
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $amt = (float) ($row['base_amount'] ?? 0);
            if ($code === '' || $amt <= 0.0001) {
                continue;
            }
            $rows[] = ['code' => $code, 'amount' => $amt];
        }

        return $rows;
    }

    public function formatAmount(float $amount): string
    {
        $order = $this->getOrder();
        $currency = $order ? (string) $order->getBaseCurrencyCode() : null;

        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $order ? $order->getStore() : null,
            $currency
        );
    }

    public function getTotalGiftAmount(): float
    {
        $sum = 0.0;
        foreach ($this->getUsageRows() as $row) {
            $sum += $row['amount'];
        }

        return $sum;
    }

    public function isAdminArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML;
        } catch (\Throwable) {
            return false;
        }
    }

    public function shouldDisplay(): bool
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }
        $total = (float) $order->getData('venbhas_giftcard_amount');
        if ($total > 0.0001) {
            return true;
        }

        return $this->getUsageRows() !== [];
    }
}
