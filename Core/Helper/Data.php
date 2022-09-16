<?php
declare(strict_types=1);

namespace ActiveCampaign\Core\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public const ACTIVE_CAMPAIGN_GENERAL_STATUS = 'active_campaign/general/status';
    public const ACTIVE_CAMPAIGN_GENERAL_API_URL = 'active_campaign/general/api_url';
    public const ACTIVE_CAMPAIGN_GENERAL_API_KEY = 'active_campaign/general/api_key';
    public const ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID = 'active_campaign/general/connection_id';

    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var \Magento\Theme\Block\Html\Header\Logo
     */
    private $logo;

    /**
     * Construct
     *
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Theme\Block\Html\Header\Logo $logo
     */
    public function __construct(
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Theme\Block\Html\Header\Logo $logo
    ) {
        parent::__construct($context);
        $this->storeRepository = $storeRepository;
        $this->configInterface = $configInterface;
        $this->logo = $logo;
    }

    /**
     * Is enabled
     *
     * @param int|string|null $scopeCode
     *
     * @return bool
     */
    public function isEnabled(int|string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::ACTIVE_CAMPAIGN_GENERAL_STATUS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * Get API URL
     *
     * @param int|string|null $scopeCode
     *
     * @return string|null
     */
    public function getApiUrl(int|string $scopeCode = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_GENERAL_API_URL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * Get API key
     *
     * @param int|string|null $scopeCode
     *
     * @return string|null
     */
    public function getApiKey(int|string $scopeCode = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_GENERAL_API_KEY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * Get connection ID
     *
     * @param int|string|null $scopeCode
     *
     * @return string|null
     */
    public function getConnectionId(int|string $scopeCode = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $scopeCode
        );
    }

    /**
     * Get store logo
     *
     * @param int|string|null $scopeCode
     *
     * @return string
     */
    public function getStoreLogo(int|string $scopeCode = null): string
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
     *
     * @return int
     */
    public function priceToCents(?float $price = 0.0): int
    {
        $price = (is_null($price)) ? $price = 0.0 : $price;
        return (int) (round($price, 2) * 100);
    }

    /**
     * Check connections
     *
     * @param array $allConnections
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function checkConnections(array $allConnections): bool
    {
        if ($allConnections['success']) {
            $activeConnectionIds = [];

            foreach ($allConnections['data']['connections'] as $connection) {
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
                            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
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
     * Save config
     *
     * @param string $path
     * @param string $value
     * @param int $scopeId
     *
     * @return void
     */
    public function saveConfig(
        string $path,
        string $value,
        int $scopeId
    ) {
        $scope = ($scopeId)
            ? \Magento\Store\Model\ScopeInterface::SCOPE_STORES
            : \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $this->configInterface->saveConfig($path, $value, $scope, $scopeId);
    }
}
