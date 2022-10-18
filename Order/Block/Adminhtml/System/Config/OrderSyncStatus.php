<?php
declare(strict_types=1);

namespace ActiveCampaign\Order\Block\Adminhtml\System\Config;

class OrderSyncStatus extends \Magento\Backend\Block\Template
{
    public const AC_SYNC_STATUS = 'ac_order_sync_status';

    /**
     * @var string
     */
    protected $_template = 'ActiveCampaign_Order::system/config/order_sync_status.phtml';

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    private $filterBuilder;

    /**
     * Construct
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;

        parent::__construct($context, $data);
    }

    /**
     * Get order count helper
     *
     * @param \Magento\Framework\Api\Filter[] $filter
     * @return int
     */
    public function getOrderCountHelper(array $filter = []): int
    {
        $searchCriteria = $this->searchCriteriaBuilder;

        if (count($filter)) {
            $searchCriteria->addFilters($filter);
        }
        $searchCriteria = $searchCriteria->create();
        $searchCriteria->setCurrentPage(1)
            ->setPageSize(1);

        return $this->orderRepository
            ->getList($searchCriteria)
            ->getTotalCount();
    }

    /**
     * Get total order
     *
     * @return int
     */
    public function getTotalOrder(): int
    {
        return $this->getOrderCountHelper();
    }

    /**
     * Get sync order
     *
     * @return int
     */
    public function getSyncOrder(): int
    {
        return $this->getOrderCountHelper([
            $this->filterBuilder
                ->setField(self::AC_SYNC_STATUS)
                ->setValue(\ActiveCampaign\Order\Model\Config\CronConfig::SYNCED)
                ->setConditionType('eq')
                ->create()
        ]);
    }

    /**
     * Get not sync order
     *
     * @return int
     */
    public function getNotSyncOrder(): int
    {
        return $this->getOrderCountHelper(
            [
                $this->filterBuilder
                    ->setField(self::AC_SYNC_STATUS)
                    ->setValue(\ActiveCampaign\Order\Model\Config\CronConfig::NOT_SYNCED)
                    ->setConditionType('eq')
                    ->create(),
                $this->filterBuilder
                    ->setField(self::AC_SYNC_STATUS)
                    ->setValue(true)
                    ->setConditionType('null')
                    ->create()
            ]
        );
    }

    /**
     * Get failed sync
     *
     * @return int
     */
    public function getFailedSync(): int
    {
        return $this->getOrderCountHelper([
            $this->filterBuilder
                ->setField(self::AC_SYNC_STATUS)
                ->setValue(\ActiveCampaign\Order\Model\Config\CronConfig::FAIL_SYNCED)
                ->setConditionType('eq')
                ->create()
        ]);
    }
}
