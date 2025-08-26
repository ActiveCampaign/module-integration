<?php
namespace ActiveCampaign\Order\Model\Config;

use function PHPUnit\Framework\isNull;

class CronConfig extends \Magento\Framework\App\Config\Value
{
    const SYNCED = 1;
    const NOT_SYNCED = 0;
    const FAIL_SYNCED = 2;
    /**
     * Cron string path
     */
    const CRON_STRING_PATH = 'crontab/default/jobs/ac_order_sync_cron_job/schedule/cron_expr';

    /**
     * Cron model path
     */
    const CRON_MODEL_PATH = 'crontab/default/jobs/ac_order_sync_cron_job/run/model';

    /**
     * @var \Magento\Framework\App\Config\ValueFactory
     */
    protected $_configValueFactory;

    /**
     * @var string
     */
    protected $_runModelPath = '';

    /**
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface      $config
     * @param \Magento\Framework\App\Cache\TypeListInterface          $cacheTypeList
     * @param \Magento\Framework\App\Config\ValueFactory              $configValueFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param string                                                  $runModelPath
     * @param array                                                   $data
     */
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

    /**
     * {@inheritdoc}
     *
     * @return $this
     * @throws \Exception
     */
    public function afterSave()
    {
        $cronMinute = $this->getData('groups/order_sync/fields/cron_minute/value');
        $cronHour = $this->getData('groups/order_sync/fields/cron_hour/value');
        $cronDay = $this->getData('groups/order_sync/fields/cron_day/value');
        $cronMonth = $this->getData('groups/order_sync/fields/cron_month/value');
        $cronWeekday = $this->getData('groups/order_sync/fields/cron_weekday/value');

        $cronMinute = ($cronMinute == null) ? '*' : $cronMinute;
        $cronHour = ($cronHour == null) ? '*' : $cronHour;
        $cronDay = ($cronDay == null) ? '*' : $cronDay;
        $cronMonth = ($cronMonth == null) ? '*' : $cronMonth;
        $cronWeekday = ($cronWeekday == null) ? '*' : $cronWeekday;

        $cronExprArray = [
            $cronMinute, //Minute
            $cronHour, //Hour
            $cronDay, //Day of the Month
            $cronMonth, //Month of the Year
            $cronWeekday, //Day of the Week
        ];

        $cronExprString = join(' ', $cronExprArray);

        try {
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH,
                'path'
            )->setValue(
                $cronExprString
            )->setPath(
                self::CRON_STRING_PATH
            )->save();
            $this->_configValueFactory->create()->load(
                self::CRON_MODEL_PATH,
                'path'
            )->setValue(
                $this->_runModelPath
            )->setPath(
                self::CRON_MODEL_PATH
            )->save();
        } catch (\Exception $e) {
            throw new \Exception(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}
