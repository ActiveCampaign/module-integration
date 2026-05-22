<?php
declare(strict_types=1);

namespace ActiveCampaign\Product\Model;

use Magento\Framework\App\ResourceConnection;

class ProductSyncFlagRepository
{
    private const TABLE = 'activecampaign_product_sync';

    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function getUnsyncedProductIds(int $storeId, int $limit): array
    {
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $select = $conn->select()
            ->from($table, ['product_id'])
            ->where('store_id = ?', $storeId)
            ->where('sync_status = ?', 0)
            ->limit($limit);
        return $conn->fetchCol($select);
    }

    public function seedFlagsForStore(int $storeId, array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $rows = [];
        foreach ($productIds as $pid) {
            $rows[] = [
                'product_id' => (int)$pid,
                'store_id' => $storeId,
                'sync_status' => 0,
                'ac_product_id' => null
            ];
        }
        $conn->insertOnDuplicate($table, $rows, ['sync_status', 'ac_product_id']);
    }

    public function seedFromAllProducts(int $storeId): void
    {
        $conn = $this->resource->getConnection();
        $prodTable = $this->resource->getTableName('catalog_product_entity');
        $ids = $conn->fetchCol($conn->select()->from($prodTable, ['entity_id']));
        $this->seedFlagsForStore($storeId, array_map('intval', $ids));
    }

    public function markSynced(int $storeId, array $productIds, ?array $acIds = null): void
    {
        if (empty($productIds)) {
            return;
        }
        $conn = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        foreach ($productIds as $index => $pid) {
            $bind = [
                'sync_status' => 1
            ];
            if ($acIds && isset($acIds[$index])) {
                $bind['ac_product_id'] = (string)$acIds[$index];
            }
            $conn->update(
                $table,
                $bind,
                ['product_id = ?' => (int)$pid, 'store_id = ?' => $storeId]
            );
        }
    }

    public function getChangedProductIds(int $storeId, int $limit): array
    {
        $conn = $this->resource->getConnection();
        $flagTable = $this->resource->getTableName(self::TABLE);
        $prodTable = $this->resource->getTableName('catalog_product_entity');
        $select = $conn->select()
            ->from(['f' => $flagTable], ['product_id'])
            ->join(['p' => $prodTable], 'p.entity_id = f.product_id', [])
            ->where('f.store_id = ?', $storeId)
            ->where('f.sync_status = ?', 1)
            ->where('p.updated_at > f.updated_at')
            ->limit($limit);
        return $conn->fetchCol($select);
    }
}
