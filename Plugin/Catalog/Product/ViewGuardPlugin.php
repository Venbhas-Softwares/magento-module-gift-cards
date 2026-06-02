<?php

declare(strict_types=1);

namespace Venbhas\GiftCard\Plugin\Catalog\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Controller\Product\View;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Venbhas\GiftCard\Model\Config\ModuleEnabledGuard;
use Venbhas\GiftCard\Model\Product\Type\GiftCard;

/**
 * Returns 404 for gift card product pages when the module is disabled for the current store.
 */
class ViewGuardPlugin
{
    /**
     * @var ModuleEnabledGuard
     */
    private ModuleEnabledGuard $moduleEnabledGuard;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ForwardFactory
     */
    private ForwardFactory $resultForwardFactory;

    /**
     * @param ModuleEnabledGuard $moduleEnabledGuard Module enabled guard
     * @param ProductRepositoryInterface $productRepository Product repository
     * @param StoreManagerInterface $storeManager Store manager
     * @param ForwardFactory $resultForwardFactory Forward result factory
     */
    public function __construct(
        ModuleEnabledGuard $moduleEnabledGuard,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        ForwardFactory $resultForwardFactory
    ) {
        $this->moduleEnabledGuard = $moduleEnabledGuard;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->resultForwardFactory = $resultForwardFactory;
    }

    /**
     * Forward to noroute for gift card product detail pages when disabled.
     *
     * @param View $subject Product view controller
     * @param callable $proceed Original execute
     *
     * @return mixed
     */
    public function aroundExecute(View $subject, callable $proceed)
    {
        $productId = (int) $subject->getRequest()->getParam('id');
        if ($productId <= 0) {
            return $proceed();
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        if ($this->moduleEnabledGuard->isEnabled($storeId)) {
            return $proceed();
        }

        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return $proceed();
        }

        if ((string) $product->getTypeId() !== GiftCard::TYPE_CODE) {
            return $proceed();
        }

        /** @var Forward $resultForward */
        $resultForward = $this->resultForwardFactory->create();
        $resultForward->setController('noroute');
        $resultForward->forward('noroute');

        return $resultForward;
    }
}
