<?php

declare(strict_types=1);

namespace ActiveCampaign\SyncLog\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class DeleteSyncLog
{

    const SYNCLOG_TABLE = "sync_log";
    const XML_PATH_ACTIVE_CAMPAIGN_SYNCLOG_ENABLE = "active_campaign/synclog/synclog_delete";

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /** @var ResourceConnection  */
    private  $connection;

    /** @var DateTime  */
    private  $dateTime;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $connection,
        DateTime $dateTime
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->connection = $connection;
        $this->dateTime = $dateTime;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        if($this->isDeletingEnabled()) {
            $connection = $this->connection->getConnection();
            $tableName = $connection->getTableName(self::SYNCLOG_TABLE);
            $currentDate = $this->dateTime->gmtDate("Y-m-d", strtotime('-7 days'));
            $whereConditions = [
                  $connection->quoteInto("creation_date < ?", $currentDate)
            ];
            $connection->delete($tableName, $whereConditions);
        }
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isDeletingEnabled($scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ACTIVE_CAMPAIGN_SYNCLOG_ENABLE,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }
}
