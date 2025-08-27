<?php

namespace ActiveCampaign\Order\Cron;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use ActiveCampaign\Order\Model\OrderData\OrderDataSend;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;

class OrderSyncCron
{
    public const DELETE_METHOD = "DELETE";
    public const URL_ENDPOINT = "ecomOrders/";

    /**
     * @var ActiveCampaignOrderHelper
     */
    private $activeCampaignHelper;

    /**
     * @var OrderDataSend
     */
    protected $orderdataSend;

    /**
     * @var CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @var State
     */
    private $state;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Curl
     */
    protected $curl;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * OrderSyncCron constructor.
     *
     * @param OrderDataSend             $orderdataSend
     * @param CollectionFactory         $orderCollectionFactory
     * @param ActiveCampaignOrderHelper $activeCampaignHelper
     * @param State                     $state
     * @param Curl                      $curl
     * @param CartRepositoryInterface   $quoteRepository
     * @param LoggerInterface           $logger
     */
    public function __construct(
        OrderDataSend $orderdataSend,
        CollectionFactory $orderCollectionFactory,
        ActiveCampaignOrderHelper $activeCampaignHelper,
        State $state,
        Curl $curl,
        CartRepositoryInterface $quoteRepository,
        LoggerInterface $logger
    ) {
        $this->orderdataSend = $orderdataSend;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->state = $state;
        $this->curl = $curl;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
    }

    /**
     * @throws NoSuchEntityException|GuzzleException
     */
    public function execute(): void
    {
        try {
            $isEnabled = $this->activeCampaignHelper->isOrderSyncEnabled();
            if ($isEnabled) {
                $OrderSyncNum = $this->activeCampaignHelper->getOrderSyncNum();
                $orderCollection = $this->_orderCollectionFactory->create()
                    ->addAttributeToSelect('*')
                    ->addFieldToFilter(
                        'ac_order_sync_status',
                        ['eq' => 0]
                    )
                    ->setPageSize($OrderSyncNum)->setOrder('main_table.entity_id', "desc");

                foreach ($orderCollection as $order) {
                    try {
                        $this->orderdataSend->orderDataSend($order);
                    } catch (NoSuchEntityException|GuzzleException $e) {
                        $this->logger->error('MODULE Order: ' . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('MODULE Order: ' . $e->getMessage());
        }
    }
}
