<?php
declare(strict_types=1);

namespace ActiveCampaign\Core\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = \Monolog\Logger::INFO;

    /**
     * File name
     *
     * @var string
     */
    protected $fileName = 'var/log/activecampaign.log';
}
