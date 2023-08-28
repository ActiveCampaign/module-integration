<?php

namespace ActiveCampaign\Customer\Cron;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Core\Helper\Data as CoreHelper;
use ActiveCampaign\Customer\Helper\Data as CustomerHelper;
use ActiveCampaign\Customer\Model\Config\CronConfig;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerResourceCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Cache\TypeListInterface;

class CustomerSync
{
    const AC_SYNC_STATUS = "ac_sync_status";

    const CONTACT_ENDPOINT = "contacts";

    const ECOM_CUSTOMER_ENDPOINT = "ecomCustomers";

    const METHOD = "POST";

    const METHOD_PUT = "PUT";

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * @var CustomerResourceCollectionFactory
     */
    protected $customerResourceCollectionFactory;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerResource
     */
    protected $customerResource;

    /**
     * @var Attribute
     */
    protected $eavAttribute;

    /**
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var CoreHelper
     */
    private $coreHelper;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheTypeList
     */
    protected $cacheTypeList;

    /**
     * CustomerSync constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     * @param CustomerResourceCollectionFactory $customerResourceCollectionFactory
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param Attribute $eavAttribute
     * @param CustomerHelper $customerHelper
     * @param CoreHelper $coreHelper
     * @param Curl $curl
     * @param LoggerInterface $logger
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerResourceCollectionFactory $customerResourceCollectionFactory,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        Attribute $eavAttribute,
        CustomerHelper $customerHelper,
        CoreHelper $coreHelper,
        Curl $curl,
        LoggerInterface $logger,
        TypeListInterface $cacheTypeList
    ) {
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->customerResourceCollectionFactory = $customerResourceCollectionFactory;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->eavAttribute = $eavAttribute;
        $this->customerHelper = $customerHelper;
        $this->coreHelper = $coreHelper;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->cacheTypeList = $cacheTypeList;
    }

    public function execute()
    {
        if ($this->customerHelper->isCustomerSyncingEnabled()) {
            $contact = [];
            $ecomCustomer = [];
            $this->updateCustomers();
            $numberOfCustomers = (int)$this->customerHelper->getNumberOfCustomers();

            $customers = $this->customerResourceCollectionFactory->create()
                ->addAttributeToFilter([
                    ['attribute' => self::AC_SYNC_STATUS,'null' => true ],
                    ['attribute' => self::AC_SYNC_STATUS,'neq' => CronConfig::SYNCED ]
                ])
                ->setPageSize($numberOfCustomers);

            foreach ($customers as $customer) {
                $contactId = 0;
                $customerId = $customer->getId();
                $ecomCustomerId = 0;
                $syncStatus = CronConfig::NOT_SYNCED;
                $contactData = $this->contactBody($customer);

                try {
                    $contactResult = $this->curl->createContacts(self::METHOD, self::CONTACT_ENDPOINT, $contactData);
                    $contactId = isset($contactResult['data']['contact']['id']) ? $contactResult['data']['contact']['id'] : null;

                    if ($contactResult['success']) {
                        $ecomCustomer['connectionid'] = $this->coreHelper->getConnectionId($customer->getStoreId());
                        $ecomCustomer['externalid'] = $customerId;
                        $ecomCustomer['email'] = $customer->getEmail();
                        $ecomCustomer['acceptsMarketing'] = 1;
                        $ecomCustomerData['ecomCustomer'] = $ecomCustomer;
                        $ecomCustomerResult = $this->curl->createContacts(
                            self::METHOD,
                            self::ECOM_CUSTOMER_ENDPOINT,
                            $ecomCustomerData
                        );
                        $ecomCustomerId = isset($ecomCustomerResult['data']['ecomCustomer']['id']) ? $ecomCustomerResult['data']['ecomCustomer']['id'] : null;
                        $syncStatus = CronConfig::SYNCED;
                    } else {
                        $syncStatus = CronConfig::FAIL_SYNCED;
                    }

                    $this->saveResult($customerId, $syncStatus, $contactId, $ecomCustomerId);
                } catch (\Exception $e) {
                    $this->logger->critical("MODULE: Customer " . $e);
                }
            }
        }
    }

    /**
     * @param $customerId
     * @param $syncStatus
     * @param $contactId
     * @param $ecomCustomerId
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    private function saveResult($customerId, $syncStatus, $contactId, $ecomCustomerId)
    {
        $customerModel = $this->customerFactory->create();
        if ($customerId) {
            $this->customerResource->load($customerModel, $customerId);
        }
        $customerModel->setAcSyncStatus($syncStatus);
        $customerModel->setAcContactId($contactId);
        $customerModel->setAcCustomerId($ecomCustomerId);

        $this->customerResource->save($customerModel);
    }

    /**
     * @param null $billingId
     * @return string|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getTelephone($billingId = null)
    {
        if ($billingId) {
            $address = $this->addressRepository->getById($billingId);
            return $address->getTelephone();
        }
        return null;
    }

    /**
     * @param $customerId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getFieldValues($customerId)
    {
        $fieldValues = [];
        $customAttributes = $this->customerRepository->getById($customerId)->getCustomAttributes();
        if (!empty($customAttributes)) {
            foreach ($customAttributes as $attribute) {
                $attributeId = $this->eavAttribute->getIdByCode(Customer::ENTITY, $attribute->getAttributeCode());
                $attributeValues['field'] = $attributeId;
                $attributeValues['value'] = $attribute->getValue();
                $fieldValues[] = $attributeValues;
            }
        }
        return $fieldValues;
    }

    public function updateCustomers(){

        $numberOfCustomers = (int)$this->customerHelper->getNumberOfCustomers();
        $customers = $this->customerResourceCollectionFactory->create()
            ->addAttributeToSelect('ac_contact_id')
            ->addAttributeToFilter( 'ac_contact_id',['neq' => null ])
            ->addAttributeToFilter( self::AC_SYNC_STATUS,['eq' => CronConfig::SYNCED ])
            ->addAttributeToFilter('updated_at',['gt' => $this->customerHelper->getLastCustomerUpdateSync()])
            ->setOrder('updated_at','asc')
            ->setPageSize($numberOfCustomers);
        foreach ($customers as $customer){
            $contactData = $this->contactBody($customer);
            $lastUpdate = $this->customerHelper->getLastCustomerUpdateSync();
            try {
                $this->curl->createContacts(self::METHOD_PUT, self::CONTACT_ENDPOINT.'/'.$customer->getAcContactId(), $contactData);
                $lastUpdate = $customer->getUpdatedAt();
            } catch (\Exception $e) {
                $this->logger->critical("MODULE: Customer  contact/sync" . $e);
            }
        }
        if(isset($lastUpdate)) {
            $this->customerHelper->setLastCustomerUpdateSync($lastUpdate);
            $this->cacheTypeList->cleanType('config');
        }
    }


    private function contactBody($customer){
        $customerId = $customer->getId();
        $contact['email'] = $customer->getEmail();
        $contact['firstName'] = $customer->getFirstname();
        $contact['lastName'] = $customer->getLastname();
        $contact['phone'] = $this->getTelephone($customer->getDefaultBilling());

        $contact['fieldValues'] = $this->getFieldValues($customerId);
        $contactData['contact'] = $contact;

        return $contactData;
    }
}
