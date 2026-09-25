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
        $orderId = $order->getId();
        $incrementId = $order->getIncrementId();

        $this->logger->info('OrderCancelSync::execute START', [
            'order_id' => $orderId,
            'increment_id' => $incrementId,
            'status' => $order->getStatus(),
            'state' => method_exists($order, 'getState') ? $order->getState() : null
        ]);

        if (!$this->activeCampaignHelper->isOrderSyncEnabled()) {
            $this->logger->info('OrderCancelSync: skip (order sync disabled in config)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId
            ]);
            return;
        }

        $realTime = $this->activeCampaignHelper->isOrderSyncInRealTime();
        if (!$realTime) {
            $this->logger->info('OrderCancelSync: skip (realtime sync disabled; relying on OrderCancelFlag + cron)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId
            ]);
            return;
        }

        $isCanceled = $order->getStatus() === 'canceled'
            || (method_exists($order, 'getState') && $order->getState() === Order::STATE_CANCELED);
        if (!$isCanceled) {
            $this->logger->info('OrderCancelSync: skip (status/state is not canceled)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'status' => $order->getStatus(),
                'state' => method_exists($order, 'getState') ? $order->getState() : null
            ]);
            return;
        }

        $previousStatus = $order->getOrigData('status');
        $previousState = method_exists($order, 'getOrigData') ? $order->getOrigData('state') : null;
        $wasAlreadyCanceled = (
            ($previousStatus !== null && $previousStatus === 'canceled')
            || ($previousState !== null && $previousState === Order::STATE_CANCELED)
        );
        $isNewCancel = !$wasAlreadyCanceled;
        if (!$isNewCancel) {
            $this->logger->info('OrderCancelSync: skip (not a new cancel transition)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'previous_status' => $previousStatus,
                'previous_state'  => $previousState
            ]);
            return;
        }

        try {
            $this->logger->info('OrderCancelSync: calling orderDataSend() (→ sendCancelledOrder GQL)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId
            ]);
            $result = $this->orderDataSend->orderDataSend($order);
            if (isset($result['success']) && $result['success'] === false) {
                $msg = $result['errorMessage'] ?? 'Unknown sync failure';
                $this->logger->error('OrderCancelSync: orderDataSend returned failure', [
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'errorMessage' => (string)$msg,
                    'result' => $result
                ]);
            } else {
                $this->logger->info('OrderCancelSync: orderDataSend success', [
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'result' => $result
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('MODULE Order OrderCancelSync: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

