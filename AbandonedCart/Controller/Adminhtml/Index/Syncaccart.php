<?php
/**
 * Copyright © Wagento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ActiveCampaign\AbandonedCart\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use ActiveCampaign\AbandonedCart\Helper\Data as AbandonedCartHelper;
use ActiveCampaign\AbandonedCart\Model\AbandonedCartSendData;

class Syncaccart extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var AbandonedCartHelper
     */
    protected $abandonedCartHelper;

    /**
     * @var AbandonedCartSendData
     */
    protected $abandonedCartSendData;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AbandonedCartHelper $abandonedCartHelper,
        AbandonedCartSendData $abandonedCartSendData
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->abandonedCartHelper = $abandonedCartHelper;
        $this->abandonedCartSendData = $abandonedCartSendData;
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();

        if ($this->abandonedCartHelper->isAbandonedCartSyncingEnabled()) {
            $response = $this->abandonedCartSendData->sendAbandonedCartData();
        }
        return $result->setData($response);
    }
}
