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
use ActiveCampaign\Core\Block\Adminhtml\System\Config\ConnectActiveCampaign;

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
     * @var ConnectActiveCampaign
     */
    protected $connectActiveCampaign;

    /**
     * @param Context               $context
     * @param JsonFactory           $resultJsonFactory
     * @param AbandonedCartHelper   $abandonedCartHelper
     * @param AbandonedCartSendData $abandonedCartSendData
     * @param ConnectActiveCampaign $connectActiveCampaign
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AbandonedCartHelper $abandonedCartHelper,
        AbandonedCartSendData $abandonedCartSendData,
        ConnectActiveCampaign $connectActiveCampaign
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->abandonedCartHelper = $abandonedCartHelper;
        $this->abandonedCartSendData = $abandonedCartSendData;
        $this->connectActiveCampaign = $connectActiveCampaign;
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        /**
 * @var Json $result
*/
        $result = $this->resultJsonFactory->create();

        if (!$this->abandonedCartHelper->isAbandonedCartSyncingEnabled()) {
            $response['error'] = __("Activecampaign status is disabled.");
        } elseif (!$this->connectActiveCampaign->isConnected()) {
            $response['error'] = __("Activecampaign is disconnected.");
        } else {
            $response = $this->abandonedCartSendData->sendAbandonedCartData();
        }
        return $result->setData($response);
    }
}
