<?php
namespace ActiveCampaign\Order\Observer;

use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use ActiveCampaign\Order\Model\OrderData\OrderDataSend;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class OrderCancelSync implements ObserverInterface
{
    private $activeCampaignHelper;
    private $orderDataSend;
    private $logger;

    public function __construct(
        ActiveCampaignOrderHelper $activeCampaignHelper,
        OrderDataSend $orderDataSend,
        LoggerInterface $logger
    ) {
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->orderDataSend = $orderDataSend;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        if (!$this->activeCampaignHelper->isOrderSyncEnabled()) {
            return;
        }

        if (!$this->activeCampaignHelper->isOrderSyncInRealTime()) {
            return;
        }

        if ($order->getStatus() !== 'canceled') {
            return;
        }

        if ($order->getOrigData('status') === 'canceled') {
            return;
        }

        try {
            $this->orderDataSend->orderDataSend($order);
        } catch (\Exception $e) {
            $this->logger->error('MODULE Order: ' . $e->getMessage());
        }
    }
}

