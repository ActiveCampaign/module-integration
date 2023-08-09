<?php

namespace ActiveCampaign\Newsletter\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const ACTIVE_CAMPAIGN_NEWSLETTER_STATUS = "active_campaign/newsletter_sync/newsletter_sync_enable";
    const ACTIVE_CAMPAIGN_NEWSLETTER_SYNC_NUM = "active_campaign/newsletter_sync/newsletter_sync_num";

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
    public function isNewslettersSyncEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_NEWSLETTER_STATUS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }


    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getNewsletterSyncNum($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_NEWSLETTER_SYNC_NUM,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }
}
