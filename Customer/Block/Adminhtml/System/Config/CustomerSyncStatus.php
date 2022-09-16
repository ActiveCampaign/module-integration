<?php
declare(strict_types=1);

namespace ActiveCampaign\Customer\Block\Adminhtml\System\Config;

class CustomerSyncStatus extends \Magento\Backend\Block\Template
{
    public const AC_SYNC_STATUS = 'ac_sync_status';

    /**
     * @var string
     */
    protected $_template = 'ActiveCampaign_Customer::system/config/customer_sync_status.phtml';

    /**
     * @var int
     */
    public $totalCustomer = 0;

    /**
     * @var int
     */
    public $syncedCustomer = 0;

    /**
     * @var int
     */
    public $notSyncedCustomer = 0;

    /**
     * @var int
     */
    public $failSyncedCustomer = 0;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

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
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param array $data
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        array $data = []
    ) {
        $this->customerRepository = $customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;

        parent::__construct($context, $data);
        $this->setSyncStatusData();
    }

    /**
     * Set sync status data
     *
     * @return CustomerSyncStatus
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setSyncStatusData()
    {
        $this->totalCustomer = $this->getTotalCustomersCount();
        $this->syncedCustomer = $this->getSyncedCustomerCount();
        $this->notSyncedCustomer = $this->getNotSyncedCustomerCount();
        $this->failSyncedCustomer = $this->getFailSyncedCustomerCount();

        return $this;
    }

    /**
     * Get customer counter helper
     *
     * @param \Magento\Framework\Api\Filter[] $filter
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCustomerCountHelper(array $filter = []): int
    {
        $searchCriteria = $this->searchCriteriaBuilder;

        if (count($filter)) {
            $searchCriteria->addFilters($filter);
        }

        $searchCriteria->setCurrentPage(1)
            ->setPageSize(1);

        return $this->customerRepository
            ->getList($searchCriteria->create())
            ->getTotalCount();
    }

    /**
     * Get total customers count
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTotalCustomersCount(): int
    {
        return $this->getCustomerCountHelper();
    }

    /**
     * Get synced customer count
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getSyncedCustomerCount(): int
    {
        return $this->getCustomerCountHelper([
            $this->filterBuilder
                ->setField(self::AC_SYNC_STATUS)
                ->setValue(\ActiveCampaign\Customer\Model\Config\CronConfig::SYNCED)
                ->setConditionType('eq')
                ->create()
        ]);
    }

    /**
     * Get not synced customer count
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getNotSyncedCustomerCount(): int
    {
        return $this->getCustomerCountHelper(
            [
                $this->filterBuilder
                    ->setField(self::AC_SYNC_STATUS)
                    ->setValue(\ActiveCampaign\Customer\Model\Config\CronConfig::NOT_SYNCED)
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
     * Get failed synced customer count
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getFailSyncedCustomerCount(): int
    {
        return $this->getCustomerCountHelper([
            $this->filterBuilder
                ->setField(self::AC_SYNC_STATUS)
                ->setValue(\ActiveCampaign\Customer\Model\Config\CronConfig::FAIL_SYNCED)
                ->setConditionType('eq')
                ->create()
        ]);
    }
}
