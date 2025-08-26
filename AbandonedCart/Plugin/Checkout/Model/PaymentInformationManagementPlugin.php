<?php

namespace ActiveCampaign\AbandonedCart\Plugin\Checkout\Model;

use ActiveCampaign\AbandonedCart\Helper\Data as AbandonedCartHelper;
use ActiveCampaign\AbandonedCart\Model\AbandonedCartSendData;

class PaymentInformationManagementPlugin
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
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param AbandonedCartHelper   $abandonedCartHelper
     * @param AbandonedCartSendData $abandonedCartSendData
     */
    public function __construct(
        AbandonedCartHelper $abandonedCartHelper,
        AbandonedCartSendData $abandonedCartSendData
    ) {
        $this->abandonedCartHelper = $abandonedCartHelper;
        $this->abandonedCartSendData = $abandonedCartSendData;
    }

    /**
     * @inheritdoc
     */
    public function afterSavePaymentInformation(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
        $result,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        ?\Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        $quoteRepository = $this->getCartRepository();
        /**
 * @var \Magento\Quote\Model\Quote $quote
*/
        $quote = $quoteRepository->getActive($cartId);

        if ($this->abandonedCartHelper->isAbandonedCartSyncingEnabled()) {
            try {
                $response = $this->abandonedCartSendData->sendAbandonedCartData($quote->getId());
            } catch (\Exception $e) {

            }
        }
    }

    /**
     * Get Cart repository
     *
     * @return     \Magento\Quote\Api\CartRepositoryInterface
     * @deprecated 100.2.0
     */
    private function getCartRepository()
    {
        if (!$this->cartRepository) {
            $this->cartRepository = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Quote\Api\CartRepositoryInterface::class);
        }
        return $this->cartRepository;
    }
}
