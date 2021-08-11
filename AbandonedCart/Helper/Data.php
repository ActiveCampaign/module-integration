<?php
namespace ActiveCampaign\AbandonedCart\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use ActiveCampaign\AbandonedCart\Model\Config\CronConfig;

class Data extends AbstractHelper
{
    const ACTIVE_CAMPAIGN_ABANDONED_CART_SYNC = "active_campaign/abandoned_cart/sync";
    const ABANDONED_CART_NUMBER_OF_ABANDONED_CART = "active_campaign/abandoned_cart/number_of_abandoned_cart";

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isAbandonedCartSyncingEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_ABANDONED_CART_SYNC,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getCronTime($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            CronConfig::CRON_MODEL_PATH,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getNumberOfAbandonedCart($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ABANDONED_CART_NUMBER_OF_ABANDONED_CART,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }
}
