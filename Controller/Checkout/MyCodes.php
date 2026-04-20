<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Controller\Checkout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Venbhas\GiftCard\Model\GiftCardCode;

/**
 * Checkout controller to fetch active gift card codes for the logged-in customer.
 */
class MyCodes implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * Initialize controller.
     *
     * @param JsonFactory $jsonFactory JSON result factory
     * @param CustomerSession $customerSession Customer session
     * @param ResourceConnection $resource Resource connection
     */
    public function __construct(
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        ResourceConnection $resource
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->resource = $resource;
    }

    /**
     * Execute action.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        $customerId = $this->customerSession->getCustomerId() ? (int) $this->customerSession->getCustomerId() : 0;
        if ($customerId < 1) {
            return $result->setData(['success' => true, 'codes' => []]);
        }

        $conn = $this->resource->getConnection();
        $codeTable = $this->resource->getTableName('venbhas_giftcard_code');

        $select = $conn->select()
            ->from($codeTable, ['code', 'balance_amount', 'currency_code'])
            ->where('redeemer_customer_id = ?', $customerId)
            ->where('status = ?', GiftCardCode::STATUS_ACTIVE)
            ->where('balance_amount > ?', 0.0001)
            ->order('updated_at DESC')
            ->limit(25);

        $rows = [];
        foreach ($conn->fetchAll($select) as $r) {
            $code = strtoupper(trim((string) ($r['code'] ?? '')));
            $amount = (float) ($r['balance_amount'] ?? 0);
            if ($code === '' || $amount <= 0.0001) {
                continue;
            }
            $rows[] = [
                'code' => $code,
                'amount' => $amount,
                'currency' => (string) ($r['currency_code'] ?? ''),
            ];
        }

        return $result->setData(['success' => true, 'codes' => $rows]);
    }
}
