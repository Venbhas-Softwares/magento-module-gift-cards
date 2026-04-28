<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Account;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\GiftCardCode;
use Venbhas\GiftCard\Model\GiftCardTransaction;

class AddGiftcard extends AbstractAccount
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DateTime
     */
    private $dateTime;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        CustomerSession $customerSession,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        DateTime $dateTime
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerSession = $customerSession;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->dateTime = $dateTime;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            if (!$this->getRequest()->isPost()) {
                throw new LocalizedException(__('Invalid request.'));
            }
            if (!$this->formKeyValidator->validate($this->getRequest())) {
                throw new LocalizedException(__('Invalid form key. Please refresh the page.'));
            }

            $customerId = (int) $this->customerSession->getCustomerId();
            if ($customerId <= 0) {
                throw new LocalizedException(__('Please sign in.'));
            }

            $customerEmail = '';
            try {
                $customer = $this->customerSession->getCustomer();
                $customerEmail = $customer && $customer->getEmail() ? (string) $customer->getEmail() : '';
            } catch (\Throwable $e) {
                $customerEmail = '';
            }
            $customerEmail = $customerEmail !== '' ? strtolower(trim($customerEmail)) : null;

            $code = strtoupper(trim((string) $this->getRequest()->getParam('giftcard_code')));
            if ($code === '') {
                throw new LocalizedException(__('Please enter a gift card code.'));
            }
            if (strlen($code) > 64) {
                throw new LocalizedException(__('Gift card code is too long.'));
            }

            $conn = $this->resource->getConnection();
            $codeTable = $this->resource->getTableName('venbhas_giftcard_code');
            $trxTable = $this->resource->getTableName('venbhas_giftcard_transaction');

            $now = $this->dateTime->gmtDate();
            $source = strtolower(trim((string) $this->getRequest()->getParam('source')));

            $conn->beginTransaction();
            try {
                $codeColumns = array_keys((array) $conn->describeTable($codeTable));
                $hasBalanceAmount = in_array('balance_amount', $codeColumns, true);
                $hasAmount = in_array('amount', $codeColumns, true);
                $hasStatus = in_array('status', $codeColumns, true);
                $hasRedeemerCustomerId = in_array('redeemer_customer_id', $codeColumns, true);
                $hasRedeemerEmail = in_array('redeemer_email', $codeColumns, true);
                $hasRedeemedBy = in_array('redeemed_by', $codeColumns, true);
                $hasRedeemedEmail = in_array('redeemed_email', $codeColumns, true);
                $hasIsReedemed = in_array('is_reedemed', $codeColumns, true);
                $hasIsRedeemed = in_array('is_redeemed', $codeColumns, true);

                // Lock the giftcard_code row.
                $selectCols = ['entity_id', 'code'];
                foreach ([
                    'balance_amount',
                    'amount',
                    'status',
                    'currency_code',
                    'redeemer_customer_id',
                    'redeemer_email',
                    'redeemed_by',
                    'redeemed_email',
                    'is_reedemed',
                    'is_redeemed',
                ] as $c) {
                    if (in_array($c, $codeColumns, true)) {
                        $selectCols[] = $c;
                    }
                }

                $gc = $conn->fetchRow(
                    $conn->select()
                        ->from($codeTable, $selectCols)
                        ->where('code = ?', $code)
                        ->forUpdate(true)
                );
                if (!$gc) {
                    throw new LocalizedException(__('Gift card code was not found.'));
                }

                $isRedeemedFlag = null;
                if ($hasIsReedemed) {
                    $isRedeemedFlag = (int) ($gc['is_reedemed'] ?? 0);
                } elseif ($hasIsRedeemed) {
                    $isRedeemedFlag = (int) ($gc['is_redeemed'] ?? 0);
                }
                if ($isRedeemedFlag === 1) {
                    throw new LocalizedException(__('This gift card code is already redeemed.'));
                }

                $creditAmount = null;
                if ($hasAmount && isset($gc['amount']) && $gc['amount'] !== null && $gc['amount'] !== '') {
                    $creditAmount = (float) $gc['amount'];
                } elseif ($hasBalanceAmount && isset($gc['balance_amount']) && $gc['balance_amount'] !== null && $gc['balance_amount'] !== '') {
                    $creditAmount = (float) $gc['balance_amount'];
                }
                if ($creditAmount === null || $creditAmount <= 0.0001) {
                    throw new LocalizedException(__('Gift card amount is invalid.'));
                }

                // Wallet balance before this credit (sum of credits - usage).
                // Important: customer_email can be NULL; do not use `customer_email = NULL` in SQL.
                $whereParts = ['customer_id = ?'];
                $bind = [$customerId];
                if ($customerEmail !== null && $customerEmail !== '') {
                    $whereParts[] = 'customer_email = ?';
                    $bind[] = $customerEmail;
                }
                $previousBalance = (float) $conn->fetchOne(
                    'SELECT COALESCE(SUM(CASE '
                    . 'WHEN transaction_type = ? THEN amount '
                    . 'WHEN transaction_type IN(?, ?) THEN -amount '
                    . 'WHEN transaction_type = ? THEN -amount '
                    . 'ELSE 0 END), 0) '
                    . 'FROM ' . $trxTable . ' WHERE (' . implode(' OR ', $whereParts) . ')',
                    array_merge(
                        [
                            GiftCardTransaction::ACTION_CREDIT,
                            GiftCardTransaction::ACTION_DEBIT,
                            GiftCardTransaction::ACTION_CHECKOUT_APPLY,
                            GiftCardTransaction::ACTION_REDEEM,
                        ],
                        $bind
                    )
                );

                $update = ['updated_at' => $now];
                if ($hasRedeemerCustomerId) {
                    $update['redeemer_customer_id'] = $customerId;
                }
                if ($hasRedeemerEmail) {
                    $update['redeemer_email'] = $customerEmail;
                }
                if ($hasRedeemedBy) {
                    $update['redeemed_by'] = $customerId;
                }
                if ($hasRedeemedEmail) {
                    $update['redeemed_email'] = $customerEmail;
                }
                if ($hasIsReedemed) {
                    $update['is_reedemed'] = 1;
                }
                if ($hasIsRedeemed) {
                    $update['is_redeemed'] = 1;
                }
                if ($hasStatus) {
                    $update['status'] = GiftCardCode::STATUS_ACTIVE;
                }
                if ($hasBalanceAmount) {
                    // Ensure balance is at least the amount we are crediting into the account.
                    $update['balance_amount'] = $creditAmount;
                    if (in_array('initial_value', $codeColumns, true)) {
                        $update['initial_value'] = $creditAmount;
                    }
                }

                $conn->update(
                    $codeTable,
                    $update,
                    ['entity_id = ?' => (int) $gc['entity_id']]
                );

                $giftcardId = (int) $gc['entity_id'];
                $currentBalance = $previousBalance + $creditAmount;

                $conn->insert($trxTable, [
                    'giftcard_id' => $giftcardId,
                    'transaction_type' => GiftCardTransaction::ACTION_CREDIT,
                    'amount' => $creditAmount,
                    'previous_balance' => $previousBalance,
                    'current_balance' => $currentBalance,
                    'description' => $source === 'checkout'
                        ? 'added a new giftcard at checkout'
                        : 'added a new giftcard in my account',
                    'order_id' => null,
                    'customer_id' => $customerId,
                    'customer_email' => $customerEmail,
                    'created_at' => $now,
                    'store_id' => (int) $this->storeManager->getStore()->getId(),
                ]);

                $conn->commit();
            } catch (\Throwable $e) {
                $conn->rollBack();
                throw $e;
            }

            return $result->setData([
                'success' => true,
                'message' => (string) __('Gift card added to your account.'),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => (string) __('Something went wrong.')]);
        }
    }
}

