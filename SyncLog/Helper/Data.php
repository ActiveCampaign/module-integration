<?php

namespace ActiveCampaign\SyncLog\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const ACTIVE_CAMPAIGN_SYNCLOG_MODE = "active_campaign/synclog/synclog_mode";
    const ACTIVE_CAMPAIGN_REMOVE_AFTER_DAYS = "active_campaign/synclog/remove_after_days";
    const XML_PATH_ACTIVE_CAMPAIGN_SYNCLOG_ENABLE = "active_campaign/synclog/synclog_delete";
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

    public function removeAfterDays(?string $scopeCode = null){
        return $this->scopeConfig->getValue(
         self::ACTIVE_CAMPAIGN_REMOVE_AFTER_DAYS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
      );
    }
    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isLogError(?string $scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_SYNCLOG_MODE,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
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
