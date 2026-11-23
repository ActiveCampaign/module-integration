<?php

namespace ActiveCampaign\AbandonedCart\Plugin\Checkout\Model;

use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use ActiveCampaign\AbandonedCart\Helper\Data as AbandonedCartHelper;
use ActiveCampaign\AbandonedCart\Model\AbandonedCartSendData;

class GuestPaymentInformationManagementPlugin
{
    /**
     * @var AbandonedCartHelper
     */
    protected $abandonedCartHelper;

    /**
     * @var AbandonedCartSendData
     */
    protected $abandonedCartSendData;

    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var QuoteIdMaskResource
     */
    private $quoteIdMaskResource;

    /**
     * @param AbandonedCartHelper                     $abandonedCartHelper
     * @param AbandonedCartSendData                   $abandonedCartSendData
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     */
    public function __construct(
        AbandonedCartHelper $abandonedCartHelper,
        AbandonedCartSendData $abandonedCartSendData,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResource $quoteIdMaskResource
    ) {
        $this->abandonedCartHelper = $abandonedCartHelper;
        $this->abandonedCartSendData = $abandonedCartSendData;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
    }

    /**
     * @param \Magento\Checkout\Model\ShippingInformationManagement $subject
     * @param $result
     * @param $cartId
     * @param $email
     * @param \Magento\Quote\Api\Data\PaymentInterface              $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface              $billingAddress
     */
    public function afterSavePaymentInformation(
        \Magento\Checkout\Model\GuestPaymentInformationManagement $subject,
        $result,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        $billingAddress = null
    ) {
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($quoteIdMask, $cartId, 'masked_id');
        if ($this->abandonedCartHelper->isAbandonedCartSyncingEnabled()) {
            try {
                $response = $this->abandonedCartSendData->sendAbandonedCartData($quoteIdMask->getQuoteId());
            } catch (\Exception $e) {

            }
        }
    }
}
