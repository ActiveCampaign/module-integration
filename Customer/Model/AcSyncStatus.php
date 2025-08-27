<?php

namespace ActiveCampaign\Customer\Model;

use ActiveCampaign\Customer\Model\Config\CronConfig;

class AcSyncStatus extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        if ($this->_options === null) {
            $this->_options = [
                [
                    'value' => CronConfig::SYNCED,
                    'label' => __('Synced')->getText(),
                ],
                [
                    'value' => CronConfig::NOT_SYNCED,
                    'label' => __('Not Synced')->getText(),
                ],
                [
                    'value' => CronConfig::FAIL_SYNCED,
                    'label' => __('Not Synced')->getText(),
                ],
            ];
        }
        return $this->_options;
    }

    /**
     * @inheritdoc
     */
    public function getAllOptions($withEmpty = true, $defaultValues = false)
    {
        return  $this->toOptionArray();
    }
}
