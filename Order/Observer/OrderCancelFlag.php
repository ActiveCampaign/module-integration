<?php
namespace ActiveCampaign\Order\Observer;

use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

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

        if ($order->getStatus() !== 'canceled') {
            return;
        }

        if ($order->getOrigData('status') === 'canceled') {
            return;
        }

        $order->setData('ac_order_sync_status', 0);
    }
}

