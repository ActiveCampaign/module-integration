<?php

namespace ActiveCampaign\SyncLog\Model;

class SyncLog extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    public const CACHE_TAG = 'sync_log';

    protected $cacheTag = 'sync_log';

    protected $eventPrefix = 'sync_log';

    protected function _construct()
    {
        $this->_init('ActiveCampaign\SyncLog\Model\ResourceModel\SyncLog');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }
}
