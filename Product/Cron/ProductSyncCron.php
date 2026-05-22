<?php

namespace ActiveCampaign\Product\Cron;

use ActiveCampaign\Product\Helper\Data as ProductHelper;
use ActiveCampaign\Product\Model\CatalogProductProvider;
use ActiveCampaign\Product\Model\ProductSync;
use ActiveCampaign\Core\Helper\Data as CoreData;
use ActiveCampaign\Product\Model\ProductSyncFlagRepository;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ProductSyncCron
{
    private $helper;
    private $provider;
    private $sync;
    private $state;
    private $logger;
    private $storeManager;
    private $coreData;
    private $flagRepository;

    public function __construct(
        ProductHelper $helper,
        CatalogProductProvider $provider,
        ProductSync $sync,
        State $state,
        StoreManagerInterface $storeManager,
        CoreData $coreData,
        ProductSyncFlagRepository $flagRepository,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->provider = $provider;
        $this->sync = $sync;
        $this->state = $state;
        $this->storeManager = $storeManager;
        $this->coreData = $coreData;
        $this->flagRepository = $flagRepository;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            foreach ($this->storeManager->getStores() as $store) {
                $storeId = (int)$store->getId();
                if (!$this->helper->isProductSyncEnabled((string)$storeId)) {
                    continue;
                }
                $limit = (int)($this->helper->getProductSyncNum((string)$storeId) ?? 0);
                $batchLimit = $limit > 0 ? $limit : 100;
                $unsyncedIds = $this->flagRepository->getUnsyncedProductIds($storeId, $batchLimit);
                $changedIds = $this->flagRepository->getChangedProductIds($storeId, $batchLimit);
                if (empty($unsyncedIds)) {
                    $this->flagRepository->seedFromAllProducts($storeId);
                    $unsyncedIds = $this->flagRepository->getUnsyncedProductIds($storeId, $batchLimit);
                }
                $batchIds = array_values(array_unique(array_merge($unsyncedIds, $changedIds)));
                if (empty($batchIds)) {
                    continue;
                }
                $products = $this->provider->buildProducts($storeId, null, $batchIds);
                if (empty($products)) {
                    continue;
                }
                $connectionId = (int)($this->coreData->getConnectionId((string)$storeId) ?? 0);
                $result = $this->sync->bulkUpsertProducts($products, $connectionId ?: null);
                if (!empty($result['success'])) {
                    $this->flagRepository->markSynced($storeId, $batchIds);
                } else {
                    $this->logger->error('MODULE Product: ' . ($result['message'] ?? 'Unknown error'));
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('MODULE Product: ' . $e->getMessage());
        }
    }
}
