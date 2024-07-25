<?php

namespace ActiveCampaign\Newsletter\Cron;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Newsletter\Helper\Data as ActiveCampaignNewsletterHelper;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection;
use Psr\Log\LoggerInterface;
use ActiveCampaign\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use function Safe\strtotime;

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
     * @var Customer
     */
    protected $customer;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerResource
     */
    protected $customerResource;

    protected $orderCollectionFactory;

    /**
     * NewsletterSyncCron constructor.
     * @param Collection $newsletterCollection
     * @param ActiveCampaignNewsletterHelper $activeCampaignHelper
     * @param State $state
     * @param Curl $curl
     * @param CartRepositoryInterface $quoteRepository
     * @param LoggerInterface $logger
     * @param Customer $custoner
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     */
    public function __construct(
        Collection $newsletterCollection,
        ActiveCampaignNewsletterHelper $activeCampaignHelper,
        State $state,
        Curl $curl,
        CartRepositoryInterface $quoteRepository,
        LoggerInterface $logger,
        Customer $custoner,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    )
    {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->newsletterCollection = $newsletterCollection;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->state = $state;
        $this->curl = $curl;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->customer = $custoner;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
    }

    /**
     * @throws NoSuchEntityException|GuzzleException
     */
    public function execute(): void
    {
        try {
            $isEnabled = $this->activeCampaignHelper->isNewslettersSyncEnabled();

            if ($isEnabled) {
                $date = $this->activeCampaignHelper->getLastSync();

                $newsletterSyncNum = $this->activeCampaignHelper->getNewsletterSyncNum();
                $newsletterCollection = $this->newsletterCollection
                    ->addFieldToSelect('*')
                    ->addFieldToFilter(
                        'change_status_at',
                        ['gt' => new \Zend_Db_Expr('FROM_UNIXTIME('.$date.')')]
                    )
                    ->addFieldToFilter(
                        'customer_id',
                        ['eq' => 0]
                    )
                    ->setOrder('change_status_at', 'desc')
                    ->setPageSize($newsletterSyncNum)
                    ->setCurPage(1);
                foreach ($newsletterCollection as $news) {
                    $acContact=NULL;
                    try {
                        $contactData = [
                                'email' => $news->getSubscriberEmail()
                        ];
                        if($news->getCustomerId()){
                            $result = $this->customer->updateCustomer($this->getCustomer($news->getCustomerId()));
                            $acContact = $result['ac_contact_id'];
                        }else{
                             //   $acContact = $this->customer->createGuestContact($contactData);
                                 $this->customer->createGuestCustomer($contactData,$news->getStoreId());
                        }

                        if ($acContact) {
                            $news->setAcNewsletterSyncId($acContact);
                            $news->setAcNewsletterSyncStatus(1);
                            $news->save();
                        }
                    } catch (NoSuchEntityException|GuzzleException $e) {
                        $this->logger->error('MODULE Order: ' . $e->getMessage());
                    }
                }
                $this->activeCampaignHelper->setLastSync(strtotime(date('Y-m-d H:i:s')));
            }
        } catch (\Exception $e) {
            $this->logger->error('MODULE Order: ' . $e->getMessage());
        }

    }
    /**
     * @param $customerId
     * @return CustomerModel
     */
    private function getCustomer($customerId): CustomerModel
    {
        $customerModel = $this->customerFactory->create();
        if (is_numeric($customerId)) {
            $this->customerResource->load($customerModel, $customerId);
            return $customerModel;
        }
        return $customerModel;
    }
}
