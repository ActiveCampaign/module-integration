<?php

declare(strict_types=1);

namespace ActiveCampaign\SyncLog\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use ActiveCampaign\SyncLog\Helper\Data;

class DeleteSyncLog
{

    const SYNCLOG_TABLE = "sync_log";




    /**
     * @var ResourceConnection
     */
    private $connection;

    /**
     * @var DateTime
     */
    private $dateTime;

    private $helper;

    public function __construct(
        ResourceConnection $connection,
        DateTime $dateTime,
        Data $helper
    ) {
        $this->connection = $connection;
        $this->dateTime = $dateTime;
        $this->helper = $helper;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {

        if ($this->helper->isDeletingEnabled()) {
            $connection = $this->connection->getConnection();
            $tableName = $connection->getTableName(self::SYNCLOG_TABLE);
            $currentDate = $this->dateTime->gmtDate("Y-m-d", strtotime('-'.$this->helper->removeAfterDays().' days'));
            $whereConditions = [
                  $connection->quoteInto("Date(creation_date) <= ?", $currentDate)
            ];

            $connection->delete($tableName, $whereConditions);
        }
    }
}
