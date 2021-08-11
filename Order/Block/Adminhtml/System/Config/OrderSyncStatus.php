<?php

namespace ActiveCampaign\Order\Block\Adminhtml\System\Config;

use ActiveCampaign\Order\Model\Config\CronConfig;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class OrderSyncStatus extends \Magento\Backend\Block\Template
{
    const AC_SYNC_STATUS = "ac_order_sync_status";

    /**
     * @var string
     */
    protected $_template = 'ActiveCampaign_Order::system/config/order_sync_status.phtml';

    /**
     * @var OrderFactory|CollectionFactory
     */
    protected $orderFactory;

    /**
     * OrderSyncStatus constructor.
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param array $data
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        Context $context,
        CollectionFactory $orderFactory,
        array $data = []
    ) {
        $this->orderFactory = $orderFactory;
        parent::__construct($context, $data);
        $this->setSyncStatusData();
    }

    /**
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOrderCollection()
    {
        $collection = $this->orderFactory->create()->addAttributeToSelect('*');
        return $collection;
    }

    /**
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSyncOrder()
    {
        $sync = $this->getOrderCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::SYNCED],
            ]
        );
        return count($sync);
    }

    /**
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTotalOrder()
    {
        $total = $this->getOrderCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::SYNCED],
                ['eq' => CronConfig::NOT_SYNCED],
                ['eq' => CronConfig::FAIL_SYNCED],
            ]
        );
        return count($total);
    }

    /**
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getNotSyncOrder()
    {
        $notSync = $this->getOrderCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::NOT_SYNCED],
            ]
        )->getData();
        return count($notSync);
    }

    /**
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFailedSync()
    {
        $failSync = $this->getOrderCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::FAIL_SYNCED],
            ]
        );
        return count($failSync);
    }
}
