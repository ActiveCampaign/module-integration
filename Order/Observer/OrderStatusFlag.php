<?php
namespace ActiveCampaign\Order\Observer;

use ActiveCampaign\Order\Model\Config\CronConfig;
use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class OrderStatusFlag implements ObserverInterface
{
    /**
     * @var ActiveCampaignOrderHelper
     */
    private $activeCampaignHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ActiveCampaignOrderHelper $activeCampaignHelper,
        LoggerInterface $logger
    ) {
        $this->activeCampaignHelper = $activeCampaignHelper;
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

        $currentStatus = (string)$order->getStatus();
        $currentState = method_exists($order, 'getState') ? (string)$order->getState() : '';
        $previousStatus = method_exists($order, 'getOrigData') ? $order->getOrigData('status') : null;
        $previousState = method_exists($order, 'getOrigData') ? $order->getOrigData('state') : null;

        $statusChanged = ($previousStatus !== $currentStatus);
        $stateChanged = ($previousState !== null && $previousState !== $currentState);
        $isNewOrder = ($previousStatus === null && $currentStatus !== '');

        if (!$statusChanged && !$stateChanged && !$isNewOrder) {
            return;
        }

        $order->setData('ac_order_sync_status', CronConfig::NOT_SYNCED);

        $this->logger->info('OrderStatusFlag: marked for re-sync (NOT_SYNCED) on status/state change', [
            'order_id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
            'previous_status' => $previousStatus,
            'previous_state' => $previousState,
            'new_status' => $currentStatus,
            'new_state' => $currentState,
            'is_new_order' => $isNewOrder
        ]);
    }
}
