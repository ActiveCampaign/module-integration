<?php

namespace ActiveCampaign\Customer\Block\Adminhtml\System\Config;

use ActiveCampaign\Customer\Model\Config\CronConfig;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerFactory;

class CustomerSyncStatus extends \Magento\Backend\Block\Template
{
    const AC_SYNC_STATUS = "ac_sync_status";

    /**
     * @var string
     */
    protected $_template = 'ActiveCampaign_Customer::system/config/customer_sync_status.phtml';

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

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
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        CustomerFactory $customerFactory,
        array $data = []
    ) {
        $this->customerFactory = $customerFactory;
        parent::__construct($context, $data);
        $this->setSyncStatusData();
    }

    /**
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setSyncStatusData()
    {
        $customersData = $this->customerFactory->create()
            ->addAttributeToFilter([
                ['attribute' => self::AC_SYNC_STATUS,'null' => true ],
                ['attribute' => self::AC_SYNC_STATUS,'eq' => CronConfig::SYNCED ],
                ['attribute' => self::AC_SYNC_STATUS,'eq' => CronConfig::NOT_SYNCED ],
                ['attribute' => self::AC_SYNC_STATUS,'eq' => CronConfig::FAIL_SYNCED ]
            ])->getData();

        $this->totalCustomer = count($customersData);
        $this->syncedCustomer = count(array_keys(array_column($customersData, self::AC_SYNC_STATUS), CronConfig::SYNCED));
        $this->notSyncedCustomer = count(array_keys(array_column($customersData, self::AC_SYNC_STATUS), CronConfig::NOT_SYNCED));
        $this->failSyncedCustomer = count(array_keys(array_column($customersData, self::AC_SYNC_STATUS), CronConfig::FAIL_SYNCED));

        return $this;
    }
}
