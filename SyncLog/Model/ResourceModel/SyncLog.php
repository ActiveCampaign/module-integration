<?php

namespace ActiveCampaign\SyncLog\Model\ResourceModel;

class SyncLog extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context
    ) {
        parent::__construct($context);
    }

    protected function _construct()
    {
        $this->_init('sync_log', 'id');
    }
}
