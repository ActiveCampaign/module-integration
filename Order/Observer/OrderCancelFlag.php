<?php
namespace ActiveCampaign\Order\Observer;

use ActiveCampaign\Order\Model\Config\CronConfig;
use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderCancelFlag implements ObserverInterface
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

        $isCanceled = $order->getStatus() === 'canceled'
            || (method_exists($order, 'getState') && $order->getState() === Order::STATE_CANCELED);
        if (!$isCanceled) {
            return;
        }

        $previousStatus = $order->getOrigData('status');
        $previousState = method_exists($order, 'getOrigData') ? $order->getOrigData('state') : null;
        $wasAlreadyCanceled = (
            ($previousStatus !== null && $previousStatus === 'canceled')
            || ($previousState !== null && $previousState === Order::STATE_CANCELED)
        );
        $isNewCancel = !$wasAlreadyCanceled;

        if ($isNewCancel) {
            $order->setData('ac_order_sync_status', CronConfig::NOT_SYNCED);
            $this->logger->info('OrderCancelFlag: marked for re-sync (NOT_SYNCED) on new cancel', [
                'order_id' => $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'previous_status' => $previousStatus,
                'previous_state'  => $previousState,
                'new_status' => $order->getStatus(),
                'new_state'  => method_exists($order, 'getState') ? $order->getState() : null
            ]);
        }
    }
}

