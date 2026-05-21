<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Sales\Order;

use Magento\Sales\Block\Order\Totals;
use Venbhas\GiftCard\Model\Config;
use Venbhas\GiftCard\Model\Order\GiftCardTotalsApplier;

/**
 * Injects gift card discount into all sales totals blocks (views and emails).
 */
class TotalsPlugin
{
    private const TOTAL_CODE = 'venbhas_giftcard';

    /**
     * Prevent re-entry when afterGetTotals refreshes the totals array.
     *
     * @var array<int, true>
     */
    private static $getTotalsInProgress = [];

    /**
     * @var Config
     */
    private $config;

    /**
     * @var GiftCardTotalsApplier
     */
    private $applier;

    /**
     * @param Config $config Module config
     * @param GiftCardTotalsApplier $applier Totals applier
     */
    public function __construct(
        Config $config,
        GiftCardTotalsApplier $applier
    ) {
        $this->config = $config;
        $this->applier = $applier;
    }

    /**
     * Ensure gift card row exists before totals HTML is built.
     *
     * @param Totals $subject Totals block
     * @param Totals $result Totals block
     * @return Totals
     */
    public function afterBeforeToHtml(Totals $subject, Totals $result): Totals
    {
        $this->applyGiftCardTotal($subject);

        return $result;
    }

    /**
     * Required by generated interceptors; applies gift row once then returns filtered totals.
     *
     * Admin templates call getTotals('footer') and getTotals('') which use numeric keys,
     * so we must not recurse unless the total was actually added to the block.
     *
     * @param Totals $subject Totals block
     * @param array $result Totals array
     * @param string|null $area Area filter
     * @return array
     */
    public function afterGetTotals(Totals $subject, array $result, $area = null): array
    {
        if ($subject->getTotal(self::TOTAL_CODE)) {
            return $result;
        }

        $key = spl_object_id($subject);
        if (isset(self::$getTotalsInProgress[$key])) {
            return $result;
        }

        self::$getTotalsInProgress[$key] = true;
        try {
            $this->applyGiftCardTotal($subject);
            if ($subject->getTotal(self::TOTAL_CODE)) {
                return $subject->getTotals($area);
            }
        } finally {
            unset(self::$getTotalsInProgress[$key]);
        }

        return $result;
    }

    /**
     * Apply gift card total row when module is enabled for the store.
     *
     * @param Totals $subject
     * @return void
     */
    private function applyGiftCardTotal(Totals $subject): void
    {
        $storeId = $this->resolveStoreId($subject);

        if (!$this->config->isEnabled($storeId)) {
            return;
        }

        $this->applier->apply($subject);
    }

    /**
     * Resolve store ID from totals block source or order.
     *
     * @param Totals $subject
     * @return int|null
     */
    private function resolveStoreId(Totals $subject): ?int
    {
        $source = $subject->getSource();
        if ($source !== null && method_exists($source, 'getStoreId')) {
            return (int) $source->getStoreId();
        }

        try {
            $order = $subject->getOrder();
            if ($order !== null) {
                return (int) $order->getStoreId();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
