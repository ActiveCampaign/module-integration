<?php
namespace ActiveCampaign\Core\Helper;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Theme\Block\Html\Header\Logo;

class Data extends AbstractHelper
{
    const ACTIVE_CAMPAIGN_GENERAL_STATUS = "active_campaign/general/status";
    const ACTIVE_CAMPAIGN_GENERAL_API_URL = "active_campaign/general/api_url";
    const ACTIVE_CAMPAIGN_GENERAL_API_KEY = "active_campaign/general/api_key";
    const ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID = "active_campaign/general/connection_id";

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var ConfigInterface
     */
    private $configInterface;

    /**
     * @var Logo
     */
    private $logo;

    /**
     * Data constructor.
     * @param StoreRepositoryInterface $storeRepository
     * @param ConfigInterface $configInterface
     * @param Context $context
     * @param Logo $logo
     */
    public function __construct(
        StoreRepositoryInterface $storeRepository,
        ConfigInterface $configInterface,
        Context $context,
        Logo $logo
    ) {
        parent::__construct($context);
        $this->storeRepository = $storeRepository;
        $this->configInterface = $configInterface;
        $this->logo = $logo;
    }

    /**
     * @param null $scopeCode
     * @return bool
     */
    public function isEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_GENERAL_STATUS,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getApiUrl($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_GENERAL_API_URL,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getApiKey($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_GENERAL_API_KEY,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return mixed
     */
    public function getConnectionId($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
            ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * @param null $scopeCode
     * @return string
     */
    public function getStoreLogo($scopeCode = null)
    {
        $folderName = \Magento\Config\Model\Config\Backend\Image\Logo::UPLOAD_DIR;
        $storeLogoPath = $this->scopeConfig->getValue(
            'design/header/logo_src',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
        $path = $folderName . '/' . $storeLogoPath;
        $logoUrl = $this->_urlBuilder->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]) . $path;

        if ($storeLogoPath !== null) {
            $url = $logoUrl;
        } else {
            $url = $this->logo->getLogoSrc();
        }

        return $url;
    }

    /**
     * Converts to cents the price amount
     *
     * @param float|null $price
     * @return int
     */
    public function priceToCents($price)
    {
        $price = (is_null($price)) ? $price = 0.0 : $price;
        return (int) (round($price, 2) * 100);
    }

    /**
     * @param $allConnections
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function checkConnections($allConnections)
    {
        if ($allConnections['success']) {
            $activeConnectionIds = [];
            foreach ($allConnections['data']['connections'] as  $connection) {
                $store = $this->storeRepository->get($connection['externalid']);
                $connectionId = $this->getConnectionId($store->getId());
                $activeConnectionIds[] = $connection['id'];
                if ($connectionId != $connection['id']) {
                    $this->saveConfig(
                        self::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                        $connection['id'],
                        $store->getId()
                    );
                }
            }

            $stores = $this->storeRepository->getList();
            foreach ($stores as $store) {
                if ($store->getId()) {
                    $connectionId = $this->getConnectionId($store->getId());
                    if (($connectionId) && (!in_array($connectionId, $activeConnectionIds))) {
                        $this->configInterface->deleteConfig(
                            self::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                            ScopeInterface::SCOPE_STORES,
                            $store->getId()
                        );
                    }
                }
            }

            return true;
        }
        return false;
    }

    /**
     * @param $path
     * @param $value
     * @param $scopeId
     */
    public function saveConfig($path, $value, $scopeId)
    {
        $scope = ($scopeId) ? ScopeInterface::SCOPE_STORES : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $this->configInterface->saveConfig($path, $value, $scope, $scopeId);
    }
}
