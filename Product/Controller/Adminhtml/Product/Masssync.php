<?php
declare(strict_types=1);

namespace ActiveCampaign\Product\Controller\Adminhtml\Product;

use ActiveCampaign\Product\Helper\Data as ProductHelper;
use ActiveCampaign\Product\Model\CatalogProductProvider;
use ActiveCampaign\Product\Model\ProductSync;
use ActiveCampaign\Product\Model\ProductSyncFlagRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\MassAction\Filter;

class Masssync extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::catalog';

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * @var CatalogProductProvider
     */
    protected $catalogProductProvider;

    /**
     * @var ProductSync
     */
    protected $productSync;

    /**
     * @var ProductSyncFlagRepository
     */
    protected $flagRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var MessageManagerInterface
     */
    protected $msgManager;

    /**
     * Masssync constructor.
     *
     * @param Context                    $context
     * @param Filter                     $filter
     * @param CollectionFactory          $collectionFactory
     * @param ProductHelper              $productHelper
     * @param CatalogProductProvider     $catalogProductProvider
     * @param ProductSync                $productSync
     * @param ProductSyncFlagRepository  $flagRepository
     * @param StoreManagerInterface      $storeManager
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        ProductHelper $productHelper,
        CatalogProductProvider $catalogProductProvider,
        ProductSync $productSync,
        ProductSyncFlagRepository $flagRepository,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->productHelper = $productHelper;
        $this->catalogProductProvider = $catalogProductProvider;
        $this->productSync = $productSync;
        $this->flagRepository = $flagRepository;
        $this->storeManager = $storeManager;
        $this->msgManager = $context->getMessageManager();
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $productIds = $collection->getAllIds();
            $countSelected = count($productIds);

            if ($countSelected === 0) {
                $this->msgManager->addNoticeMessage(__('No products selected.'));
                return $resultRedirect->setPath('catalog/product/index');
            }

            $defaultStore = $this->storeManager->getDefaultStoreView();
            if (!$defaultStore) {
                $stores = $this->storeManager->getStores(false, true);
                $defaultStore = $stores ? reset($stores) : null;
            }
            if (!$defaultStore) {
                $this->msgManager->addErrorMessage(
                    __('Could not resolve a store view for product sync.')
                );
                return $resultRedirect->setPath('catalog/product/index');
            }
            $storeId = (int)$defaultStore->getId();

            if (!$this->productHelper->isProductSyncEnabled()) {
                $this->msgManager->addErrorMessage(
                    __('ActiveCampaign Product Sync is disabled. Please enable it in configuration.')
                );
                return $resultRedirect->setPath('catalog/product/index');
            }

            $payloads = $this->catalogProductProvider->buildProducts(
                $storeId,
                null,
                $productIds
            );

            if (empty($payloads)) {
                $this->msgManager->addErrorMessage(
                    __('No product payloads could be built for the selected products.')
                );
                return $resultRedirect->setPath('catalog/product/index');
            }

            $this->flagRepository->seedFlagsForStore($storeId, $productIds);

            $result = $this->productSync->bulkUpsertProducts($payloads);

            $countSynced = 0;
            $syncedIds = [];
            if (!empty($result['success'])) {
                $countSynced = count($payloads);
                $syncedIds = $productIds;
            }

            if (!empty($syncedIds)) {
                $this->flagRepository->markSynced($storeId, $syncedIds);
            }

            $countFailed = $countSelected - $countSynced;

            if ($countSynced > 0) {
                $this->msgManager->addSuccessMessage(
                    __('Products synced: %1', $countSynced)
                );
            }
            if ($countFailed > 0) {
                $msg = __('Products failed to sync: %1', $countFailed);
                if (!empty($result['message'])) {
                    $msg .= ' — ' . __($result['message']);
                }
                $this->msgManager->addErrorMessage($msg);
            }
        } catch (\Throwable $e) {
            $this->msgManager->addErrorMessage(
                __('Product sync error: %1', $e->getMessage())
            );
        }

        return $resultRedirect->setPath('catalog/product/index');
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
