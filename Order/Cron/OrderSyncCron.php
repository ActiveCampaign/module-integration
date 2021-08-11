<?php

namespace ActiveCampaign\Order\Cron;

use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use ActiveCampaign\Order\Model\OrderData\OrderDataSend;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\App\State;
use Magento\Quote\Api\CartRepositoryInterface;
use ActiveCampaign\Core\Helper\Curl;

class OrderSyncCron
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
     * OrderSyncCron constructor.
     * @param OrderDataSend $orderdataSend
     * @param CollectionFactory $orderCollectionFactory
     * @param ActiveCampaignOrderHelper $activeCampaignHelper
     * @param State $state
     * @param Curl $curl
     */
    public function __construct(
        OrderDataSend $orderdataSend,
        CollectionFactory $orderCollectionFactory,
        ActiveCampaignOrderHelper $activeCampaignHelper,
        State $state,
        Curl $curl,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->orderdataSend = $orderdataSend;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->state = $state;
        $this->curl = $curl;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @return $this
     */
    public function execute()
    {
        $isEnabled = $this->activeCampaignHelper->isOrderSyncEnabled();
        if ($isEnabled) {
            $OrderSyncNum = $this->activeCampaignHelper->getOrderSyncNum();
            $orderCollection = $collection = $this->_orderCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToSelect('*')
                ->addFilter(
                    'ac_order_sync_status',
                    '0',
                    'eq'
                )
                ->setPageSize($OrderSyncNum);
            foreach ($orderCollection as $order) {
                $this->orderdataSend->orderDataSend($order);
                $quote = $this->quoteRepository->get($order->getQuoteId());
                if ($quote->getAcOrderSyncId() != 0)  {
                    $this->curl->orderDataDelete(self::DELETE_METHOD, self::URL_ENDPOINT, $quote->getAcOrderSyncId());
                }
            }
            return $this;
        } else {
            return $this;
        }
    }
}
