<?php
/**
 * Copyright © Wagento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ActiveCampaign\AbandonedCart\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use ActiveCampaign\AbandonedCart\Model\AbandonedCartSendData;

class Sync extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Quote\Model\Quote
     */
    private $quoteModel;

    /**
     * @var AbandonedCartSendData
     */
    protected $abandonedCartSendData;

    /**
     * @param \Magento\Backend\App\Action $context
     * @param \Magento\Quote\Model\Quote $quoteModel
     * @param AbandonedCartSendData $abandonedCartSendData
     */
    public function __construct(
        Action\Context $context,
        \Magento\Quote\Model\Quote $quoteModel,
        AbandonedCartSendData $abandonedCartSendData
    ) {
        $this->quoteModel = $quoteModel;
        $this->abandonedCartSendData = $abandonedCartSendData;
        parent::__construct($context);
    }

    /**
     * Sync action
     *
     * @return void
     */
    public function execute()
    {
        // check if we know what should be synced
        $id = $this->getRequest()->getParam('entity_id');
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {
            $title = "";
            try {
                // init model and sync
                $model = $this->quoteModel;
                $model->load($id);

                $result = $this->abandonedCartSendData->sendAbandonedCartData($model->getEntityId());
                if(isset($result['error'])){
                    $this->messageManager->addErrorMessage($result['error']);
                }else{
                    $this->messageManager->addSuccessMessage(__('The data has been synced.'));
                }

                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/');
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find a data to sync.'));
        return $resultRedirect->setPath('*/*/');
    }
}
