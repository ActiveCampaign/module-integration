<?php
namespace ActiveCampaign\Order\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const ACTIVE_CAMPAIGN_ORDER_STATUS = "active_campaign/order_sync/order_sync_enable";
    const ACTIVE_CAMPAIGN_ORDER_SYNC_NUM = "active_campaign/order_sync/order_sync_num";
    const ACTIVE_CAMPAIGN_ORDER_SYNC_REAL_TIME = "active_campaign/order_sync/order_sync_real_time";

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
    ) {
        parent::__construct($context);
        $this->state = $state;
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isOrderSyncEnabled(?string $scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_ORDER_STATUS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isOrderSyncInRealTime(?string $scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_ORDER_SYNC_REAL_TIME,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getOrderSyncNum(?string $scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_ORDER_SYNC_NUM,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }
}
