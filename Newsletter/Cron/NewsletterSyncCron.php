<?php

namespace ActiveCampaign\Newsletter\Cron;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Newsletter\Helper\Data as ActiveCampaignNewsletterHelper;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection;
use Psr\Log\LoggerInterface;

class NewsletterSyncCron
{
    const METHOD = "POST";
    const CONTACT_ENDPOINT = "contact/sync";

    /**
     * @var ActiveCampaignOrderHelper
     */
    private $activeCampaignHelper;

    /**
     * @var OrderDataSend
     */
    protected $orderdataSend;

    /**
     * @var Collection
     */
    protected $newsletterCollection;

    /**
     * @var State
     */
    private $state;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Curl
     */
    protected $curl;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * OrderSyncCron constructor.
     * @param Collection $newsletterCollection
     * @param ActiveCampaignNewsletterHelper $activeCampaignHelper
     * @param State $state
     * @param Curl $curl
     * @param CartRepositoryInterface $quoteRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Collection $newsletterCollection,
        ActiveCampaignNewsletterHelper $activeCampaignHelper,
        State $state,
        Curl $curl,
        CartRepositoryInterface $quoteRepository,
        LoggerInterface $logger
    )
    {
        $this->newsletterCollection = $newsletterCollection;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->state = $state;
        $this->curl = $curl;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
    }

    /**
     * @throws NoSuchEntityException|GuzzleException
     */
    public function execute(): void
    {
        try {
            $isEnabled = $this->activeCampaignHelper->isNewslettersSyncEnabled();

            if ($isEnabled) {
                $newsletterSyncNum = $this->activeCampaignHelper->getNewsletterSyncNum();
                $newsletterCollection = $this->newsletterCollection
                    ->addFieldToSelect('*')
                    ->addFieldToFilter(
                        'ac_newsletter_sync_status',
                        ['neq' => 1]
                    )
                    ->addFieldToFilter(
                        'customer_id',
                        ['eq' => 0]
                    )
                    ->setPageSize($newsletterSyncNum)
                    ->setCurPage(1);

                foreach ($newsletterCollection as $news) {

                    try {
                        $contactData = [
                            'contact' => [
                                'email' => $news->getSubscriberEmail()
                            ]
                        ];
                        $contactResult = $this->curl->createContacts(self::METHOD, self::CONTACT_ENDPOINT, $contactData);
                        if (isset($contactResult['data']['contact']['id'])) {
                            $news->setAcNewsletterSyncId($contactResult['data']['contact']['id']);
                            $news->setAcNewsletterSyncStatus(1);
                            $news->save();
                        }
                    } catch (NoSuchEntityException|GuzzleException $e) {
                        $this->logger->error('MODULE Order: ' . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('MODULE Order: ' . $e->getMessage());
        }

    }
}
