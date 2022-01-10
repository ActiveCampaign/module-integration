<?php
namespace ActiveCampaign\Core\Controller\Adminhtml\System\Config;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Core\Helper\Data as ActiveCampaignHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\PageCache\Model\Cache\Type;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Disconnect extends Action
{
    const URL_ENDPOINT = "connections";
    const METHOD = "DELETE";
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
     * @var TypeListInterface
     */
    private $cacheTypeList;

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
     * @var Curl
     */
    private $curl;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Connection constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param TypeListInterface $cacheTypeList
     * @param ConfigInterface $configInterface
     * @param StoreRepositoryInterface $storeRepository
     * @param StoreManagerInterface $storeManager
     * @param ActiveCampaignHelper $activeCampaignHelper
     * @param Curl $curl
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        TypeListInterface $cacheTypeList,
        ConfigInterface $configInterface,
        StoreRepositoryInterface $storeRepository,
        StoreManagerInterface $storeManager,
        ActiveCampaignHelper $activeCampaignHelper,
        Curl $curl
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
     */
    public function execute()
    {
        $request = $this->getRequest()->getParams();

        // Set store to default if Single store mode
        $request['store'] = $request['store'] ?? $this->storeManager->getDefaultStoreView()->getId();

        $return = [];
        $return['success'] = false;

        if ($request['status'] && !empty($request['api_url']) && !empty($request['api_key'])) {
            try {
                if ((int)$request['store']) {
                    $connectionId = $this->activeCampaignHelper->getConnectionId($request['store']);
                    $urlEndpoint = self::URL_ENDPOINT . "/" . $connectionId;
                    $result = $this->curl->deleteConnection(self::METHOD, $urlEndpoint);
                    if ($result['success']) {
                        $this->configInterface->deleteConfig(
                            ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                            ScopeInterface::SCOPE_STORES,
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
                            $connectionId = $this->activeCampaignHelper->getConnectionId($store->getId());
                            $urlEndpoint = self::URL_ENDPOINT . "/" . $connectionId;
                            $result = $this->curl->deleteConnection(self::METHOD, $urlEndpoint);
                            if ($result['success']) {
                                $this->configInterface->deleteConfig(
                                    ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                                    ScopeInterface::SCOPE_STORES,
                                    $store->getId()
                                );
                            } else {
                                $return['success'] = false;
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

        if ($return['success'] === true) {
            $this->cacheTypeList->invalidate([
                Config::TYPE_IDENTIFIER,
                Type::TYPE_IDENTIFIER
            ]);
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($return);
    }
}
