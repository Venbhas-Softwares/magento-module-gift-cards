<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Block\Adminhtml\Customer;

use Magento\Backend\Block\Template\Context;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Registry;
use Magento\Ui\Component\Layout\Tabs\TabWrapper;

/**
 * Ajax-loaded customer edit tab wrapper (same pattern as Orders tab).
 */
class GiftCardTransactionsTab extends TabWrapper
{
    protected Registry $coreRegistry;
    protected $isAjaxLoaded = true;

    public function __construct(Context $context, Registry $registry, array $data = [])
    {
        $this->coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    public function canShowTab()
    {
        return (bool) $this->coreRegistry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
    }

    public function getTabLabel()
    {
        return __('Gift Card Transactions');
    }

    public function getTabUrl()
    {
        return $this->getUrl('venbhas_giftcard/customer/transactions', ['_current' => true]);
    }
}

