<?php
declare(strict_types=1);

namespace ActiveCampaign\Product\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ProductStatus extends Column
{
    private const FLAG_TABLE = 'activecampaign_product_sync';
    private const STATUS_NOT_SYNCED = 0;
    private const STATUS_SYNCED = 1;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @param ContextInterface   $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resource
     * @param array              $components
     * @param array              $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resource,
        array $components = [],
        array $data = []
    ) {
        $this->resource = $resource;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }

        $productIds = [];
        foreach ($dataSource['data']['items'] as $item) {
            if (isset($item['entity_id'])) {
                $productIds[] = (int)$item['entity_id'];
            }
        }

        $statusMap = [];
        if (!empty($productIds)) {
            $conn = $this->resource->getConnection();
            $table = $this->resource->getTableName(self::FLAG_TABLE);
            $select = $conn->select()
                ->from($table, ['product_id', 'sync_status'])
                ->where('product_id IN (?)', $productIds);
            foreach ($conn->fetchAll($select) as $row) {
                $pid = (int)$row['product_id'];
                $statusMap[$pid] = (int)$row['sync_status'];
            }
        }

        $columnName = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                $item[$columnName] = __('Not Synced');
                continue;
            }
            $pid = (int)$item['entity_id'];
            $status = $statusMap[$pid] ?? self::STATUS_NOT_SYNCED;
            $item[$columnName] = $status === self::STATUS_SYNCED
                ? __('Synced')
                : __('Not Synced');
        }

        return $dataSource;
    }
}
