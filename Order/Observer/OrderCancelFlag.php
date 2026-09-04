<?php
namespace ActiveCampaign\Order\Observer;

use ActiveCampaign\Order\Model\Config\CronConfig;
use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class OrderCancelFlag implements ObserverInterface
{
    private $activeCampaignHelper;

    public function __construct(
        ActiveCampaignOrderHelper $activeCampaignHelper
    ) {
        $this->activeCampaignHelper = $activeCampaignHelper;
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

        if ($isNewCancel && $needsSync) {
            $order->setData('ac_order_sync_status', CronConfig::NOT_SYNCED);
        }
    }
}

