<?php
namespace ActiveCampaign\Customer\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use ActiveCampaign\Customer\Model\Config\CronConfig;
use \Magento\Framework\App\Config\ConfigResource\ConfigInterface;
class Data extends AbstractHelper
{
    const ACTIVE_CAMPAIGN_CUSTOMER_SYNC = "active_campaign/customer/sync";
    const ACTIVE_CAMPAIGN_CUSTOMER_NUMBER_OF_CUSTOMERS = "active_campaign/customer/number_of_customers";
    const ACTIVE_CAMPAIGN_CUSTOMER_UPDATE_LAST_SYNC = "active_campaign/customer/last_customers_updated";
    const ACTIVE_CAMPAIGN_CUSTOMER_MAP_CUSTOM_FIELDS = "active_campaign/customer/map_custom_fields";

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;
    /**
     * Data constructor.
     * @param Context $context
     */


    public function __construct(
        Context $context,
        ConfigInterface $configInterface
    ) {
        $this->configInterface = $configInterface;
        parent::__construct($context);
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isCustomerSyncingEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_CUSTOMER_SYNC,
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
    public function getNumberOfCustomers($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_CUSTOMER_NUMBER_OF_CUSTOMERS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function getLastCustomerUpdateSync($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_CUSTOMER_UPDATE_LAST_SYNC,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function getMapCustomFields($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_CUSTOMER_MAP_CUSTOM_FIELDS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    public function setLastCustomerUpdateSync($date, $scopeCode = null)
    {
        $scope = \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $this->configInterface->saveConfig(self::ACTIVE_CAMPAIGN_CUSTOMER_UPDATE_LAST_SYNC, $date, $scope);
    }
}
