<?php

namespace ActiveCampaign\AbandonedCart\Ui\Component\Listing\Column;

use ActiveCampaign\AbandonedCart\Model\Config\CronConfig;

use Magento\Ui\Component\Listing\Columns\Column;

class AcSyncStatus extends Column
{
    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = $this->getFieldLabel($item);
            }
        }
        return $dataSource;
    }

    /**
     * Retrieve field label
     *
     * @param  array $item
     * @return string
     */
    private function getFieldLabel(array $item)
    {
        $acSyncStatus = (int)$item['ac_sync_status'];
        if ($acSyncStatus === CronConfig::SYNCED) {
            return __('Synced');
        } elseif ($acSyncStatus === CronConfig::NOT_SYNCED) {
            return __('Not Synced');
        } elseif ($acSyncStatus === CronConfig::FAIL_SYNCED) {
            return __('Not Synced');
        }
        return __('Something Wrong');
    }
}
