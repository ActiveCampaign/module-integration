<?php
namespace ActiveCampaign\Order\Observer;

use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use ActiveCampaign\Order\Model\OrderData\OrderDataSend;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order as OrderModel;
use Psr\Log\LoggerInterface;

class OrderSync implements ObserverInterface
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
     * @var Curl
     */
    protected $curl;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var OrderModel
     */
    private $orderModel;

    /**
     * @var OrderResource
     */
    private $orderResource;

    /**
     * OrderSync constructor.
     *
     * @param OrderDataSend             $orderdataSend
     * @param Curl                      $curl
     * @param ActiveCampaignOrderHelper $activeCampaignHelper
     * @param OrderModel                $orderModel
     * @param CartRepositoryInterface   $quoteRepository
     * @param LoggerInterface           $logger
     * @param OrderResource $orderResource
     */
    public function __construct(
        OrderDataSend $orderdataSend,
        Curl $curl,
        ActiveCampaignOrderHelper $activeCampaignHelper,
        OrderModel $orderModel,
        CartRepositoryInterface $quoteRepository,
        LoggerInterface $logger,
        OrderResource $orderResource
    ) {
        $this->orderdataSend = $orderdataSend;
        $this->curl = $curl;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->orderModel = $orderModel;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->orderResource = $orderResource;
    }

    /**
     * @param  Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $isEnabled = $this->activeCampaignHelper->isOrderSyncEnabled();
        $synInRealTime = $this->activeCampaignHelper->isOrderSyncInRealTime();

        $orderIds = $observer->getEvent()->getOrderIds();

        if ($isEnabled && $synInRealTime) {
            foreach ($orderIds as $orderId) {
                $orderData = $this->orderModel;
                $this->orderResource->load($orderData, $orderId);
                $acOrderStatus = $orderData->getAcOrderSyncStatus();
                if ($acOrderStatus == 0) {
                    $this->orderdataSend->orderDataSend($orderData);
                }
            }
        }
    }
}
