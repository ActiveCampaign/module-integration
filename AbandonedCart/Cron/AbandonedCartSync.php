<?php

namespace ActiveCampaign\AbandonedCart\Cron;

use ActiveCampaign\AbandonedCart\Helper\Data as AbandonedCartHelper;
use ActiveCampaign\AbandonedCart\Model\AbandonedCartSendData;

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

    public function execute()
    {
        if ($this->abandonedCartHelper->isAbandonedCartSyncingEnabled()) {
            $this->abandonedCartSendData->sendAbandonedCartData();
        }
    }
}
