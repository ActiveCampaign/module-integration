<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace ActiveCampaign\AbandonedCart\Controller\Adminhtml\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use ActiveCampaign\AbandonedCart\Model\AbandonedCartSendData;

class MassSync extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'ActiveCampaign_AbandonedCart::abandonedcart_operation';

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var AbandonedCartSendData
     */
    protected $abandonedCartSendData;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param AbandonedCartSendData $abandonedCartSendData
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        AbandonedCartSendData $abandonedCartSendData
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->abandonedCartSendData = $abandonedCartSendData;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $countSync = 0;
        $countFailSync = 0;
        $countAlreadySync = 0;

        foreach ($collection as $quote) {
            $ac_sync_status = $quote->getData('ac_sync_status');
            if ($ac_sync_status) {
                $countAlreadySync++;
            } else {
                $quoteId = $quote->getEntityId();
                $result = $this->abandonedCartSendData->sendAbandonedCartData($quoteId);
                if (array_key_exists('success', $result)) {
                    $countSync++;
                } else if (array_key_exists('error', $result)) {
                    $countFailSync++;
                }
            }
        }

        if ($countSync || $countFailSync) {
            $this->messageManager->addNoticeMessage(__(
                'Orders synced: %1 Orders failed: %2',
                $countSync,
                $countFailSync
            ));
        }
        if ($countAlreadySync) {
            $this->messageManager->addNoticeMessage(__('%1 order(s) had already been synced.', $countAlreadySync));
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}
