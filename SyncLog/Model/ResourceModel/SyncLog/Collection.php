<?php

namespace ActiveCampaign\SyncLog\Model\ResourceModel\SyncLog;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $idFieldName = 'id';
    protected $eventPrefix = 'sync_log';
    protected $eventObject = 'sync_log';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('ActiveCampaign\SyncLog\Model\SyncLog', 'ActiveCampaign\SyncLog\Model\ResourceModel\SyncLog');
    }
}
