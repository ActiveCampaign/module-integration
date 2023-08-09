<?php
declare(strict_types=1);

namespace ActiveCampaign\Core\Controller\Adminhtml\System\Config;

class Disconnect extends \Magento\Backend\App\Action
{
    public const URL_ENDPOINT = 'connections';
    public const METHOD = 'DELETE';
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
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    private $cacheTypeList;

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
     * @var \ActiveCampaign\Core\Helper\Curl
     */
    private $curl;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * Construct
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface
     * @param \Magento\Store\Api\StoreRepositoryInterface $storeRepository
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \ActiveCampaign\Core\Helper\Data $activeCampaignHelper
     * @param \ActiveCampaign\Core\Helper\Curl $curl
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \ActiveCampaign\Core\Helper\Data $activeCampaignHelper,
        \ActiveCampaign\Core\Helper\Curl $curl
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cacheTypeList = $cacheTypeList;
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function execute()
    {
        $request = $this->getRequest()->getParams();

        // Set store to default if Single store mode
        $request['store'] = $request['store'] ?? $this->storeManager->getDefaultStoreView()->getId();

        $return = [];
        $return['success'] = false;

        if ($request['status']
            && !empty($request['api_url'])
            && !empty($request['api_key'])
        ) {
            try {
                if ((int)$request['store']) {
                    $connectionId = $this->activeCampaignHelper->getConnectionId($request['store']);
                    $urlEndpoint = self::URL_ENDPOINT . '/' . $connectionId;
                    $result = $this->curl->deleteConnection(self::METHOD, $urlEndpoint);

                    if ($result['success'] || str_contains($result['message'],'403 Forbidden')) {
                        $this->configInterface->deleteConfig(
                            \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                            $request['store']
                        );
                    } else {
                        $return['success'] = false;
                        $return['errorMessage'] = $result['message'];
                    }
                } elseif ($request['store'] == '0') {
                    $stores = $this->storeRepository->getList();

                    foreach ($stores as $store) {
                        if ($store->getId()) {
                            $connectionId = $this->activeCampaignHelper->getConnectionId((int)$store->getId());
                            $urlEndpoint = self::URL_ENDPOINT . '/' . $connectionId;
                            $result = $this->curl->deleteConnection(self::METHOD, $urlEndpoint);

                            if ($result['success'] || str_contains($result['message'],'403 Forbidden')) {
                                $this->configInterface->deleteConfig(
                                    \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                                    \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                                    $store->getId()
                                );
                            }else{
                                $return['success'] = false;
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
            } catch (\Exception $e) {
                $return['success'] = false;
                $return['errorMessage'] = __($e->getMessage());
            }
        }

       // if ($return['success'] === true) {
            $this->cacheTypeList->invalidate([
                \Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER,
                \Magento\PageCache\Model\Cache\Type::TYPE_IDENTIFIER
            ]);
            $this->cacheTypeList->cleanType(\Magento\Framework\App\Cache\Type\Config::TYPE_IDENTIFIER);
        //}

        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData($return);
    }
}
