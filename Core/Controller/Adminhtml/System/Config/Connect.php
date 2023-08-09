<?php
declare(strict_types=1);

namespace ActiveCampaign\Core\Controller\Adminhtml\System\Config;

class Connect extends \Magento\Backend\App\Action
{
    public const URL_ENDPOINT = 'connections';
    public const METHOD = 'POST';
    public const GET_METHOD = 'GET';

    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'ActiveCampaign_Core::config_active_campaign';

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    private $configInterface;

    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var \ActiveCampaign\Core\Helper\Data
     */
    private $activeCampaignHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \ActiveCampaign\Core\Helper\Curl
     */
    private $curl;

    private $cacheTypeList;


    /**
     * Connect constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \ActiveCampaign\Core\Helper\Data $activeCampaignHelper
     * @param \ActiveCampaign\Core\Helper\Curl $curl
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \ActiveCampaign\Core\Helper\Data $activeCampaignHelper,
        \ActiveCampaign\Core\Helper\Curl $curl,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configInterface = $configInterface;
        $this->storeRepository = $storeRepository;
        $this->storeManager = $storeManager;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->curl = $curl;
        $this->cacheTypeList = $cacheTypeList;

    }

    /**
     * Check for connection to server
     *
     * @return \Magento\Framework\Controller\Result\Json
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function execute()
    {
        $request = $this->getRequest()->getParams();

        // Set store to default if Single store mode
        $request['store'] = $request['store'] ?? $this->storeManager->getDefaultStoreView()->getId();

        $return = [];

        if ($request['status']
            && !empty($request['api_url'])
            && !empty($request['api_key'])
        ) {
            $isEnabled = $this->activeCampaignHelper->isEnabled($request['status']);
            if (!$isEnabled) {
                $this->saveConfig(
                    \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_STATUS,
                    $request['status'],
                    (int)$request['store']
                );
            }

            $apiUrl = $this->activeCampaignHelper->getApiUrl($request['store']);
            if (empty($apiUrl)) {
                $this->saveConfig(
                    \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_API_URL,
                    $request['api_url'],
                    (int)$request['store']
                );
            }

            $apiKey = $this->activeCampaignHelper->getApiKey($request['store']);
            if (empty($apiKey)) {
                $this->saveConfig(
                    \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_API_KEY,
                    $request['api_key'],
                    (int)$request['store']
                );
            }

            try {
                if ((int)$request['store']) {
                    $data = $this->curlRequestData((int)$request['store']);
                    $result = $this->curl->createConnection(
                        self::METHOD,
                        self::URL_ENDPOINT,
                        $request,
                        $data
                    );

                    if ($result['success']) {
                        $connectionId = $result['data']['connection']['id'];
                        $this->saveConfig(
                            \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                            $connectionId,
                            (int)$request['store']
                        );
                    } else {
                        $return['success'] = false;
                        $return['errorMessage'] = $result['message'];
                    }
                } elseif ($request['store'] == '0') {
                    $stores = $this->storeRepository->getList();

                    foreach ($stores as $store) {
                        if ($store->getId()) {
                            $data = $this->curlRequestData((int)$store->getId());
                            $result = $this->curl->createConnection(
                                self::METHOD,
                                self::URL_ENDPOINT,
                                $request,
                                $data
                            );

                            if ($result['success']) {
                                $return['success'] = true;
                                $connectionId = $result['data']['connection']['id'];
                                $this->saveConfig(
                                    \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                                    $connectionId,
                                    (int)$store->getId()
                                );
                            } else {
                                $return['errorMessage'] = $result['message'];
                            }
                        }
                    }
                }

                $allConnections = $this->curl->getAllConnections(
                    self::GET_METHOD,
                    self::URL_ENDPOINT
                );

                $checkConnections = $this->activeCampaignHelper->checkConnections($allConnections);
                $return['success'] = $checkConnections;
                $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);

            } catch (\Exception $e) {
                $return['success'] = false;
                $return['errorMessage'] = __($e->getMessage());
            }
        }

        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData($return);
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
    protected function saveConfig(
        string $path,
        string $value,
        int $scopeId
    ) {
        $scope = ($scopeId)
            ? \Magento\Store\Model\ScopeInterface::SCOPE_STORES
            : \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $this->configInterface->saveConfig($path, $value, $scope, $scopeId);
    }

    /**
     * CURL request data
     *
     * @param int|null $storeId
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function curlRequestData(?int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);

        return [
            'connection' => [
                'service'       => 'magento2-' . $store->getName(),
                'externalid'    => $store->getCode(),
                'name'          => $store->getName(),
                'logoUrl'       => $this->activeCampaignHelper->getStoreLogo($storeId),
                'linkUrl'       => $store->getBaseUrl()
            ]
        ];
    }
}
