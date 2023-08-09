<?php
declare(strict_types=1);

namespace ActiveCampaign\Newsletter\Block\Adminhtml\System\Config;

class NewsletterSyncStatus extends \Magento\Backend\Block\Template
{
    public const AC_SYNC_STATUS = 'ac_newsletter_sync_status';

    /**
     * @var string
     */
    protected $_template = 'ActiveCampaign_Newsletter::system/config/newsletter_sync_status.phtml';

    /**
     * @var \Magento\Newsletter\Model\ResourceModel\Subscriber\Collection
     */
    private $newsletterCollection;

    /**
     * Construct
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Newsletter\Model\ResourceModel\Subscriber\Collection $newsletterCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Newsletter\Model\ResourceModel\Subscriber\Collection $newsletterCollection,
        array $data = []
    )
    {
        $this->newsletterCollection = $newsletterCollection;

        parent::__construct($context, $data);
    }

    /**
     * Get order count helper
     * @param array $filter
     * @return int
     */
    public function getNewsletterCountHelper(array $filter = []): int
    {

        $this->newsletterCollection->clear()->getSelect()->reset(\Magento\Framework\Db\Select::WHERE);
        if (count($filter)) {
            foreach ($filter as $fil) {
                $this->newsletterCollection->addFieldToFilter($fil['field'], $fil['value']);
            }
        }

        $this->newsletterCollection->setCurPage(1)->setPageSize(1);

        return $this->newsletterCollection->getSize();
    }

    /**
     * Get total order
     * @return int
     */
    public function getTotalNewsletter(): int
    {
        return $this->getNewsletterCountHelper();
    }

    /**
     * Get sync order
     *
     * @return int
     */
    public function getSyncNewsletter(): int
    {


        return $this->getNewsletterCountHelper(
            [
                ['field' => self::AC_SYNC_STATUS,
                    'value' => ['eq' => \ActiveCampaign\Newsletter\Model\Config\CronConfig::SYNCED]
                ]
            ]
        );
    }

    /**
     * Get not sync order
     *
     * @return int
     */
    public function getNotSyncNewsletter(): int
    {

        return $this->getNewsletterCountHelper([
                [
                    'field' => self::AC_SYNC_STATUS,
                    'value' => ['neq' => \ActiveCampaign\Order\Model\Config\CronConfig::SYNCED]
                ]
            ]
        );
    }

    /**
     * Get failed sync
     *
     * @return int
     */
    public function getFailedSync(): int
    {

        return $this->getNewsletterCountHelper([
                [
                    'field' => self::AC_SYNC_STATUS,
                    'value' => ['eq' => \ActiveCampaign\Order\Model\Config\CronConfig::FAIL_SYNCED]
                ]
            ]
        );

    }
}
