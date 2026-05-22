<?php
namespace ActiveCampaign\Product\Model\Config;

class CronConfig extends \Magento\Framework\App\Config\Value
{
    const CRON_STRING_PATH = 'crontab/default/jobs/ac_product_sync_cron_job/schedule/cron_expr';
    const CRON_MODEL_PATH = 'crontab/default/jobs/ac_product_sync_cron_job/run/model';

    protected $_configValueFactory;
    protected $_runModelPath = '';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        ?string $runModelPath = '',
        ?array $data = []
    ) {
        $this->_runModelPath = $runModelPath;
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function afterSave()
    {
        $cronMinute = $this->getData('groups/product_sync/fields/cron_minute/value');
        $cronHour = $this->getData('groups/product_sync/fields/cron_hour/value');
        $cronDay = $this->getData('groups/product_sync/fields/cron_day/value');
        $cronMonth = $this->getData('groups/product_sync/fields/cron_month/value');
        $cronWeekday = $this->getData('groups/product_sync/fields/cron_weekday/value');

        $cronMinute = ($cronMinute == null) ? '*' : $cronMinute;
        $cronHour = ($cronHour == null) ? '*' : $cronHour;
        $cronDay = ($cronDay == null) ? '*' : $cronDay;
        $cronMonth = ($cronMonth == null) ? '*' : $cronMonth;
        $cronWeekday = ($cronWeekday == null) ? '*' : $cronWeekday;

        $cronExprArray = [$cronMinute, $cronHour, $cronDay, $cronMonth, $cronWeekday];
        $cronExprString = join(' ', $cronExprArray);

        try {
            $this->_configValueFactory->create()->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)->setPath(self::CRON_STRING_PATH)->save();
            $this->_configValueFactory->create()->load(self::CRON_MODEL_PATH, 'path')
                ->setValue($this->_runModelPath)->setPath(self::CRON_MODEL_PATH)->save();
        } catch (\Exception $e) {
            throw new \Exception(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}
