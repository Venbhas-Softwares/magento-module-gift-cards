<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Ui\DataProvider;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Venbhas\GiftCard\Model\ResourceModel\GiftCardTransaction\Grid\Collection as TransactionGridCollection;

/**
 * Data provider for gift card transactions filtered to current customer edit page.
 */
class CustomerTransactionDataProvider extends AbstractDataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        ObjectManagerInterface $objectManager,
        RequestInterface $request,
        CustomerRepositoryInterface $customerRepository,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $objectManager->create(TransactionGridCollection::class);

        $customerId = (int) $request->getParam('id');
        if ($customerId <= 0) {
            return;
        }

        $email = null;
        try {
            $email = (string) $customerRepository->getById($customerId)->getEmail();
        } catch (\Throwable $e) {
            $email = null;
        }

        // Backward compat: some rows may only have customer_email.
        // Use fully-qualified columns because the grid collection joins other tables
        // (e.g. customer_grid_flat) that can also have customer_id columns.
        $select = $this->collection->getSelect();
        $select->where('main_table.customer_id = ?', $customerId);
        $select->orWhere('main_table.customer_email = ?', $email ?: '__no_such_email__');
    }
}

