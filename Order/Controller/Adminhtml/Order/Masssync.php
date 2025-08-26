<?php

namespace ActiveCampaign\Order\Controller\Adminhtml\Order;

use ActiveCampaign\Order\Model\OrderData\OrderDataSend;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Quote\Api\CartRepositoryInterface;
use ActiveCampaign\Core\Helper\Curl;

class Masssync extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
{
    public const DELETE_METHOD = "DELETE";
    public const URL_ENDPOINT = "ecomOrders/";

    /**
     * @var OrderDataSend
     */
    protected $orderdataSend;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * MassSync constructor.
     *
     * @param Context                  $context
     * @param Filter                   $filter
     * @param OrderDataSend            $orderdataSend
     * @param CollectionFactory        $collectionFactory
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        Context $context,
        Filter $filter,
        OrderDataSend $orderdataSend,
        CollectionFactory $collectionFactory,
        OrderManagementInterface $orderManagement,
        Curl $curl,
        CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct($context, $filter);
        $this->orderdataSend = $orderdataSend;
        $this->collectionFactory = $collectionFactory;
        $this->orderManagement = $orderManagement;
        $this->curl = $curl;
        $this->quoteRepository = $quoteRepository;
    }

    protected function massAction(AbstractCollection $collection)
    {
        $countUpdateOrder = 0;
        $countAlreadySync = 0;
        foreach ($collection->getItems() as $order) {
            if (!$order->getEntityId()) {
                continue;
            }
            $ac_order_sync_status = $order->getData('ac_order_sync_status');
            if (!$ac_order_sync_status) {
                $result = $this->orderdataSend->orderDataSend($order);
                if (array_key_exists('success', $result) && $result['success'] != false) {
                    $countUpdateOrder++;
                }

            } else {
                $countAlreadySync++;
            }
        }
        $countNonUpdateOrder = $collection->count() - $countUpdateOrder - $countAlreadySync;
        if ($countUpdateOrder || $countNonUpdateOrder) {
            $this->messageManager->addNoticeMessage(
                __(
                    'Orders synced: %1 Orders failed: %2',
                    $countUpdateOrder,
                    $countNonUpdateOrder
                )
            );
        }
        if ($countAlreadySync) {
            $this->messageManager->addNoticeMessage(__('%1 order(s) had already been synced.', $countAlreadySync));
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($this->getComponentRefererUrl());
        return $resultRedirect;
    }
}
