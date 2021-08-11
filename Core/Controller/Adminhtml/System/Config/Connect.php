<?php
namespace ActiveCampaign\Core\Controller\Adminhtml\System\Config;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Core\Helper\Data as ActiveCampaignHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Connect extends Action
{
    const URL_ENDPOINT = "connections";
    const METHOD = "POST";
    const GET_METHOD = "GET";

    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'ActiveCampaign_Core::config_active_campaign';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var ConfigInterface
     */
    private $configInterface;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var ActiveCampaignHelper
     */
    private $activeCampaignHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * Connection constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ConfigInterface $configInterface
     * @param StoreRepositoryInterface $storeRepository
     * @param StoreManagerInterface $storeManager
     * @param ActiveCampaignHelper $activeCampaignHelper
     * @param Curl $curl
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ConfigInterface $configInterface,
        StoreRepositoryInterface $storeRepository,
        StoreManagerInterface $storeManager,
        ActiveCampaignHelper $activeCampaignHelper,
        Curl $curl
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configInterface = $configInterface;
        $this->storeRepository = $storeRepository;
        $this->storeManager = $storeManager;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->curl = $curl;
    }

    /**
     * Check for connection to server
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $request = $this->getRequest()->getParams();
        $return = [];

        if ($request['status'] && !empty($request['api_url']) && !empty($request['api_key'])) {
            $isEnabled = $this->activeCampaignHelper->isEnabled($request['status']);
            if (!$isEnabled) {
                $this->saveConfig(
                    ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_STATUS,
                    $request['status'],
                    $request['store']
                );
            }

            $apiUrl = $this->activeCampaignHelper->getApiUrl($request['store']);
            if (empty($apiUrl)) {
                $this->saveConfig(
                    ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_API_URL,
                    $request['api_url'],
                    $request['store']
                );
            }

            $apiKey = $this->activeCampaignHelper->getApiKey($request['store']);
            if (empty($apiKey)) {
                $this->saveConfig(
                    ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_API_KEY,
                    $request['api_key'],
                    $request['store']
                );
            }

            try {
                if ((int)$request['store']) {
                    $data = $this->curlRequestData($request['store']);
                    $result = $this->curl->createConnection(self::METHOD, self::URL_ENDPOINT, $request, $data);
                    if ($result['success']) {
                        $connectionId = $result['data']['connection']['id'];
                        $this->saveConfig(
                            ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                            $connectionId,
                            $request['store']
                        );
                    } else {
                        $return['success'] = false;
                        $return['errorMessage'] = $result['message'];
                    }
                } elseif ($request['store'] == "0") {
                    $stores = $this->storeRepository->getList();
                    foreach ($stores as $store) {
                        if ($store->getId()) {
                            $data = $this->curlRequestData($store->getId());
                            $result = $this->curl->createConnection(self::METHOD, self::URL_ENDPOINT, $request, $data);
                            if ($result['success']) {
                                $return['success'] = true;
                                $connectionId = $result['data']['connection']['id'];
                                $this->saveConfig(
                                    ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                                    $connectionId,
                                    $store->getId()
                                );
                            } else {
                                $return['errorMessage'] = $result['message'];
                            }
                        }
                    }
                }

                $allConnections = $this->curl->getAllConnections(self::GET_METHOD, self::URL_ENDPOINT);
                $checkConnections = $this->activeCampaignHelper->checkConnections($allConnections);
                $return['success'] = $checkConnections;
            } catch (\Exception $e) {
                $return['success'] = false;
                $return['errorMessage'] = __($e->getMessage());
            }
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($return);
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

    /**
     * @param $storeId
     * @return array[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function curlRequestData($storeId)
    {
        $store = $this->storeManager->getStore($storeId);

        return [
            "connection" => [
                "service" => $store->getName(),
                "externalid" => $store->getCode(),
                "name" => $store->getName(),
                "logoUrl" => $this->activeCampaignHelper->getStoreLogo($storeId),
                "linkUrl" => $store->getBaseUrl()
            ]
        ];
    }
}
