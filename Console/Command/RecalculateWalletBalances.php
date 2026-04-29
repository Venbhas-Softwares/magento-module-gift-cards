<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Venbhas\GiftCard\Model\GiftCardTransaction;

/**
 * Recalculate previous_balance/current_balance for wallet ledger rows.
 *
 * Wallet rule:
 * - credit: +amount
 * - debit / checkout_apply / redeem: -amount
 *
 * Grouping rule:
 * - if customer_id exists (>0): group by customer_id
 * - else group by customer_email (guest)
 *
 * Ordering rule within group:
 * - created_at asc, entity_id asc
 */
class RecalculateWalletBalances extends Command
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource Resource connection
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
        parent::__construct();
    }

    /**
     * Configure command metadata.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('venbhas:giftcard:recalc-wallet')
            ->setDescription('Recalculate wallet balances in venbhas_giftcard_transaction');
    }

    /**
     * Recalculate running wallet balances for transaction rows.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->resource->getConnection();
        $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');

        $rows = $conn->fetchAll(
            'SELECT entity_id, transaction_type, amount, customer_id, customer_email, created_at'
            . ' FROM ' . $trxTable
            . ' ORDER BY customer_id ASC, customer_email ASC, created_at ASC, entity_id ASC'
        );

        if (!$rows) {
            $output->writeln('<info>No rows found.</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Recalculating balances for ' . count($rows) . ' rows…</info>');

        $balanceByKey = [];
        $updated = 0;

        $conn->beginTransaction();
        try {
            foreach ($rows as $r) {
                $entityId = (int) $r['entity_id'];
                $type = (string) ($r['transaction_type'] ?? '');
                $amount = (float) ($r['amount'] ?? 0);
                $customerId = isset($r['customer_id']) ? (int) $r['customer_id'] : 0;
                $email = strtolower(trim((string) ($r['customer_email'] ?? '')));

                $key = $customerId > 0 ? ('cid:' . $customerId) : ('email:' . $email);
                if ($key === 'email:') {
                    // Unattributed row; keep as-is (but still set to 0-based running if desired).
                    $key = 'unknown';
                }

                $prev = (float) ($balanceByKey[$key] ?? 0.0);

                $delta = 0.0;
                if ($amount > 0.0001) {
                    if ($type === GiftCardTransaction::ACTION_CREDIT) {
                        $delta = $amount;
                    } elseif ($type === GiftCardTransaction::ACTION_DEBIT
                        || $type === GiftCardTransaction::ACTION_CHECKOUT_APPLY
                        || $type === GiftCardTransaction::ACTION_REDEEM
                    ) {
                        $delta = -$amount;
                    }
                }

                $cur = max(0.0, $prev + $delta);
                $balanceByKey[$key] = $cur;

                $conn->update(
                    $trxTable,
                    [
                        'previous_balance' => $prev,
                        'current_balance' => $cur,
                    ],
                    ['entity_id = ?' => $entityId]
                );
                $updated++;
            }

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            $output->writeln('<error>Failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Updated ' . $updated . ' rows.</info>');
        return Command::SUCCESS;
    }
}
