<?php

namespace ActiveCampaign\Customer\Model;

use \ActiveCampaign\Customer\Model\Config\CronConfig;

class AcSyncStatus implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => CronConfig::SYNCED,
                'label' => __('Synced'),
            ],
            [
                'value' => CronConfig::NOT_SYNCED,
                'label' => __('Not Synced'),
            ],
            [
                'value' => CronConfig::FAIL_SYNCED,
                'label' => __('Not Synced'),
            ],
        ];
    }
}
