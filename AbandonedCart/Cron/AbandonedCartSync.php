<?php

namespace ActiveCampaign\AbandonedCart\Cron;

use ActiveCampaign\AbandonedCart\Helper\Data as AbandonedCartHelper;
use ActiveCampaign\AbandonedCart\Model\AbandonedCartSendData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AbandonedCartSync
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
     * Abandoned cart sync constructor.
     * @param AbandonedCartHelper $abandonedCartHelper
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
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(): void
    {
        if ($this->abandonedCartHelper->isAbandonedCartSyncingEnabled()) {
            $this->abandonedCartSendData->sendAbandonedCartData();
        }
    }
}
