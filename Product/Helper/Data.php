<?php
namespace ActiveCampaign\Product\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const ACTIVE_CAMPAIGN_PRODUCT_STATUS = "active_campaign/product_sync/product_sync_enable";
    const ACTIVE_CAMPAIGN_PRODUCT_SYNC_NUM = "active_campaign/product_sync/product_sync_num";

    private $state;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\State $state
    ) {
        parent::__construct($context);
        $this->state = $state;
    }

    public function isProductSyncEnabled(?string $scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_PRODUCT_STATUS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function getProductSyncNum(?string $scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_PRODUCT_SYNC_NUM,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }
}
