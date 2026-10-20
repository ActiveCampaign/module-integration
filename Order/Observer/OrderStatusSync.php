<?php
namespace ActiveCampaign\Order\Observer;

use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use ActiveCampaign\Order\Model\OrderData\OrderDataSend;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class OrderStatusSync implements ObserverInterface
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

        $currentStatus = (string)$order->getStatus();
        $currentState = method_exists($order, 'getState') ? (string)$order->getState() : '';
        $previousStatus = method_exists($order, 'getOrigData') ? $order->getOrigData('status') : null;
        $previousState = method_exists($order, 'getOrigData') ? $order->getOrigData('state') : null;

        $this->logger->info('OrderStatusSync::execute START', [
            'order_id' => $orderId,
            'increment_id' => $incrementId,
            'status' => $currentStatus,
            'state' => $currentState,
            'previous_status' => $previousStatus,
            'previous_state' => $previousState
        ]);

        if (!$this->activeCampaignHelper->isOrderSyncEnabled()) {
            $this->logger->info('OrderStatusSync: skip (order sync disabled in config)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId
            ]);
            return;
        }

        $realTime = $this->activeCampaignHelper->isOrderSyncInRealTime();
        if (!$realTime) {
            $this->logger->info('OrderStatusSync: skip (realtime sync disabled; relying on OrderStatusFlag + cron)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId
            ]);
            return;
        }

        $statusChanged = ($previousStatus !== $currentStatus);
        $stateChanged = ($previousState !== null && $previousState !== $currentState);
        $isNewOrder = ($previousStatus === null && $currentStatus !== '');

        if (!$statusChanged && !$stateChanged && !$isNewOrder) {
            $this->logger->info('OrderStatusSync: skip (no status/state transition)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'previous_status' => $previousStatus,
                'previous_state' => $previousState
            ]);
            return;
        }

        try {
            $this->logger->info('OrderStatusSync: calling orderDataSend() (→ upsertOrder GQL)', [
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'new_status' => $currentStatus,
                'new_state' => $currentState
            ]);
            $result = $this->orderDataSend->orderDataSend($order);
            if (isset($result['success']) && $result['success'] === false) {
                $msg = $result['errorMessage'] ?? 'Unknown sync failure';
                $this->logger->error('OrderStatusSync: orderDataSend returned failure', [
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'errorMessage' => (string)$msg,
                    'result' => $result
                ]);
            } else {
                $this->logger->info('OrderStatusSync: orderDataSend success', [
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'result' => $result
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('MODULE Order OrderStatusSync: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
