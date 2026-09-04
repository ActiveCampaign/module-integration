<?php
namespace ActiveCampaign\Order\Observer;

use ActiveCampaign\Order\Model\Config\CronConfig;
use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use ActiveCampaign\Order\Model\OrderData\OrderDataSend;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
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

        $isCanceled = $order->getStatus() === 'canceled'
            || (method_exists($order, 'getState') && $order->getState() === Order::STATE_CANCELED);
        if (!$isCanceled) {
            return;
        }

        $currentSyncStatus = (int)$order->getData('ac_order_sync_status');
        $previousStatus = $order->getOrigData('status');
        $isNewCancel = $previousStatus === null
            || $previousStatus !== 'canceled';
        $needsSync = $currentSyncStatus !== CronConfig::SYNCED;

        if (!$isNewCancel || !$needsSync) {
            return;
        }

        try {
            $this->orderDataSend->orderDataSend($order);
        } catch (\Exception $e) {
            $this->logger->error('MODULE Order: ' . $e->getMessage());
        }
    }
}

