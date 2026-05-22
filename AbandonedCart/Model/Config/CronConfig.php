<?php
/**
 * @category  ActiveCampaign
 * @package   ActiveCampaign_AbandonedCart
 * @author    ActiveCampaign
 * @license   OSL-3.0, AFL-3.0
 * @link      https://www.activecampaign.com
 */
namespace ActiveCampaign\AbandonedCart\Model\Config;

/**
 * Cron config backend model.
 */
class CronConfig extends \Magento\Framework\App\Config\Value
{
    const SYNCED = 1;
    const NOT_SYNCED = 0;
    const FAIL_SYNCED = 2;

    /**
     * Cron string path.
     */
    const CRON_STRING_PATH = 'crontab/default/jobs/ac_abandoned_cart_sync_cron_job/schedule/cron_expr';

    /**
     * Cron model path.
     */
    const CRON_MODEL_PATH = 'crontab/default/jobs/ac_abandoned_cart_sync_cron_job/run/model';

    /**
     * Config resource writer.
     *
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    protected $configWriter;

    /**
     * Run model path.
     *
     * @var string
     */
    protected $runModelPath = '';

    /**
     * Constructor.
     *
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface      $config
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configWriter
     * @param \Magento\Framework\App\Cache\TypeListInterface          $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param string                                                  $runModelPath
     * @param array                                                   $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configWriter,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        ?string $runModelPath = '',
        ?array $data = []
    ) {
        $this->runModelPath = $runModelPath;
        $this->configWriter = $configWriter;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * {@inheritdoc}
     * @return $this
     * @throws \Exception
     */
    public function afterSave()
    {
        $cronMinute = $this->getData('groups/abandoned_cart/fields/cron_minute/value');
        $cronHour = $this->getData('groups/abandoned_cart/fields/cron_hour/value');
        $cronDay = $this->getData('groups/abandoned_cart/fields/cron_day/value');
        $cronMonth = $this->getData('groups/abandoned_cart/fields/cron_month/value');
        $cronWeekday = $this->getData('groups/abandoned_cart/fields/cron_weekday/value');

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
            $this->configWriter->saveConfig(self::CRON_STRING_PATH, $cronExprString);
            $this->configWriter->saveConfig(self::CRON_MODEL_PATH, $this->runModelPath);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}
