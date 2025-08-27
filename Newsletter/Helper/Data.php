<?php

namespace ActiveCampaign\Newsletter\Helper;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const ACTIVE_CAMPAIGN_NEWSLETTER_STATUS = "active_campaign/newsletter_sync/newsletter_sync_enable";
    const ACTIVE_CAMPAIGN_NEWSLETTER_SYNC_NUM = "active_campaign/newsletter_sync/newsletter_sync_num";
    const ACTIVE_CAMPAING_NEWSLETTER_LAST_SYNC = "active_campaign/newsletter_sync/last_newsletter_updated";

    /**
     * @var \Magento\Framework\App\State *
     */
    private $state;

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * Data constructor.
     *
     * @param Context                                        $context
     * @param \Magento\Framework\App\State                   $state
     * @param ConfigInterface                                $configInterface
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     */
    public function __construct(
        Context $context,
        \Magento\Framework\App\State $state,
        ConfigInterface $configInterface,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
    ) {
        parent::__construct($context);
        $this->configInterface = $configInterface;
        $this->cacheTypeList = $cacheTypeList;
        $this->state = $state;
    }

    /**
     * @param  null $scopeCode
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
     * @param  null $scopeCode
     * @return bool
     */
    public function getLastSync($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAING_NEWSLETTER_LAST_SYNC,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function setLastSync($date, $scopeCode = null)
    {
        $scope = \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $this->configInterface->saveConfig(self::ACTIVE_CAMPAING_NEWSLETTER_LAST_SYNC, $date, $scope);
        $this->cacheTypeList->invalidate(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
    }

    /**
     * @param  null $scopeCode
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
