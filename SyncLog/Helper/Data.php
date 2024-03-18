<?php

namespace ActiveCampaign\SyncLog\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const ACTIVE_CAMPAIGN_SYNCLOG_MODE = "active_campaign/synclog/synclog_mode";


    /**
     * @var \Magento\Framework\App\State *
     */
    private $state;

    /**
     * Data constructor.
     * @param Context $context
     */
    public function __construct(
        Context $context,
        \Magento\Framework\App\State $state
    )
    {
        parent::__construct($context);
        $this->state = $state;
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isLogError($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_SYNCLOG_MODE,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

}
