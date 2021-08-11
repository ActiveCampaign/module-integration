<?php
namespace ActiveCampaign\Order\Observer;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use ActiveCampaign\Order\Model\OrderData\OrderDataSend;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Quote\Api\CartRepositoryInterface;

class OrderSync implements ObserverInterface
{
    const DELETE_METHOD = "DELETE";
    const URL_ENDPOINT = "ecomOrders/";

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
     * OrderSync constructor.
     * @param OrderDataSend $orderdataSend
     * @param ActiveCampaignOrderHelper $activeCampaignHelper
     */
    public function __construct(
        OrderDataSend $orderdataSend,
        Curl $curl,
        ActiveCampaignOrderHelper $activeCampaignHelper,
        OrderModel $orderModel,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->orderdataSend = $orderdataSend;
        $this->curl = $curl;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->orderModel = $orderModel;
        $this->quoteRepository = $quoteRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $isEnabled = $this->activeCampaignHelper->isOrderSyncEnabled();
        $orderIds = $observer->getEvent()->getOrderIds();
        if ($isEnabled) {
            foreach ($orderIds as $orderId) {
                $orderData = $this->orderModel->load($orderId);
                $acOrderStatus = $orderData->getAcOrderSyncStatus();
                if ($acOrderStatus == 0) {
                    $this->orderdataSend->orderDataSend($orderData);
                }

                $quote = $this->quoteRepository->get($orderData->getQuoteId());
                $this->curl->orderDataDelete(self::DELETE_METHOD, self::URL_ENDPOINT, $quote->getAcOrderSyncId());
                if ($orderData->getStatus() == 'canceled') {
                    $orderSyncId = $orderData->getAcOrderSyncId();
                    $this->curl->orderDataDelete(self::DELETE_METHOD, self::URL_ENDPOINT, $orderSyncId);
                }
            }
        }
    }
}
