<?php

namespace ActiveCampaign\Customer\Model;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Core\Helper\Data as CoreHelper;
use ActiveCampaign\Customer\Helper\Data as CustomerHelper;
use ActiveCampaign\Customer\Model\Config\CronConfig;
use \Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerResourceCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Customer\Model\Customer as MageCustomer;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;

class Customer
{
    const AC_CUSTOMER_ID = 'ac_customer_id';

    const AC_SYNC_STATUS = "ac_sync_status";

    const CONTACT_ENDPOINT = "contacts";

    const ECOM_CUSTOMER_ENDPOINT = "ecomCustomers";

    const METHOD = "POST";
    const GET_METHOD = "GET";
    const METHOD_PUT = "PUT";



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
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Attribute
     */
    protected $eavAttribute;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * Customer constructor.
     *
     * @param CustomerResourceCollectionFactory $customerResourceCollectionFactory
     * @param CustomerRepositoryInterface       $customerRepositoryInterface
     * @param CustomerFactory                   $customerFactory
     * @param CustomerResource                  $customerResource
     * @param CustomerHelper                    $customerHelper
     * @param CoreHelper                        $coreHelper
     * @param Curl                              $curl
     * @param Attribute                         $eavAttribute
     * @param TypeListInterface                 $cacheTypeList
     * @param LoggerInterface                   $logger
     * @param SubscriberFactory                 $subscriberFactory
     * @param AddressRepositoryInterface        $addressRepository
     */
    public function __construct(
        CustomerResourceCollectionFactory $customerResourceCollectionFactory,
        CustomerRepositoryInterface $customerRepositoryInterface,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        CustomerHelper $customerHelper,
        CoreHelper $coreHelper,
        Curl $curl,
        Attribute $eavAttribute,
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger,
        SubscriberFactory $subscriberFactory,
        AddressRepositoryInterface $addressRepository,
        StoreManagerInterface $storeManager
    ) {

        $this->customerResourceCollectionFactory = $customerResourceCollectionFactory;
        $this->customerRepository = $customerRepositoryInterface;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->customerHelper = $customerHelper;
        $this->coreHelper = $coreHelper;
        $this->curl = $curl;
        $this->cacheTypeList = $cacheTypeList;
        $this->eavAttribute = $eavAttribute;
        $this->logger = $logger;
        $this->subscriberFactory = $subscriberFactory;
        $this->addressRepository = $addressRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * @param  $customerId
     * @return MageCustomer
     */
    public function getCustomerById($customerId)
    {
        $customerModel = $this->customerFactory->create();
        $this->customerResource->load($customerModel, $customerId);

        return $customerModel;
    }


    /**
     * @param  $customer
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getEcomCustomerData($customer)
    {
        $ecomCustomer = [];
        $ecomCustomer['connectionid'] = $this->coreHelper->getConnectionId($customer->getStoreId());
        $ecomCustomer['externalid'] = $customer->getId();
        $ecomCustomer['email'] = $customer->getEmail();
        $ecomCustomer['acceptsMarketing'] = (int)$this->subscriberFactory->create()->loadBySubscriberEmail($customer->getEmail(), $customer->getWebsiteId())->isSubscribed();
        $ecomCustomerData['ecomCustomer'] = $ecomCustomer;

        return $ecomCustomerData;
    }

    /**
     * @param  null $billingId
     * @return string|null
     */
    private function getTelephone($billingId = null)
    {
        if ($billingId) {
            try {
                $address = $this->addressRepository->getById($billingId);
                return $address->getTelephone();
            } catch (\Exception $exception) {

            }
        }
        return null;
    }

    public function getFieldValues($customer)
    {
        $fieldValues = [];
        $customAttributes = $this->customerHelper->getMapCustomFields();
        if (!empty($customAttributes)) {
            foreach (json_decode($customAttributes) as $attribute) {
                $attributeValues=[];
                $attributeValues['field'] = $attribute->ac_customer_field_id;
                $attributeValues['value'] ='';
                if (strncmp($attribute->customer_field_id, 'shipping__', 10) === 0) {
                    if ($customer->getDefaultShippingAddress()) {
                        $attributeValues['value'] = $customer->getDefaultShippingAddress()->getData(substr($attribute->customer_field_id, 10));
                    }
                } elseif (strncmp($attribute->customer_field_id, 'billing__', 9) === 0) {
                    if ($customer->getDefaultBillingAddress()) {
                        $attributeValues['value'] = $customer->getDefaultBillingAddress()->getData(substr($attribute->customer_field_id, 9));
                    }
                } else {
                    if ($attr = $customer->getResource()->getAttribute($attribute->customer_field_id)) {
                        $options = $attr->getOptions();
                        $attributeValues['value'] = $attr->getFrontend()->getValue($customer);
                        if (!$attributeValues['value'] && is_Array($options) && count($options)> 0) {
                            $option = current(array_filter($options, fn($o) => $o->getValue() === $customer->getData($attribute->customer_field_id)));
                            if ($option) {
                                $attributeValues['value'] = $option->getLabel();
                            }
                        }
                    } else {
                        $attributeValues['value'] = $customer->getData($attribute->customer_field_id);
                    }
                }
                $fieldValues[] = $attributeValues;
            }
        }
        return $fieldValues;
    }

    /**
     * @param  $customerId
     * @param  $syncStatus
     * @param  $contactId
     * @param  $ecomCustomerId
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function saveResult($customerId, $syncStatus, $contactId, $ecomCustomerId)
    {
        $customerModel = $this->customerFactory->create();
        if ($customerId) {
            $this->customerResource->load($customerModel, $customerId);
        }

        $customerModel->setAcSyncStatus($syncStatus);
        if ($contactId) {
            $customerModel->setAcContactId($contactId);
        }
        if ($ecomCustomerId) {
            $customerModel->setAcCustomerId($ecomCustomerId);
        }
        $this->customerResource->save($customerModel);
    }


    public function contactBody($customer)
    {
        $contact['email'] = $this->normalizeEmail((string)$customer->getEmail());
        $contact['firstName'] = $this->normalizeName((string)$customer->getFirstname());
        $contact['lastName'] = $this->normalizeName((string)$customer->getLastname());
        $contact['phone'] = $this->sanitizePhone((string)$this->getTelephone($customer->getDefaultBilling()));
        $contact['fieldValues'] = $this->getFieldValues($customer);
        $contactData['contact'] = $contact;

        return $contactData;
    }

    private function getCustomerValidationError(?string $email = null, ?string $firstName = null, ?string $lastName = null): ?string
    {
        $email = $this->normalizeEmail((string)$email);
        $firstName = $this->normalizeName((string)$firstName);
        $lastName = $this->normalizeName((string)$lastName);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Customer email is empty or invalid.';
        }

        if ($firstName === '' || $lastName === '') {
            return 'Customer first name or last name is empty.';
        }

        return null;
    }

    private function normalizeEmail(string $email): string
    {
        $email = html_entity_decode($email, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $email = strip_tags($email);
        $email = preg_replace('/[\x{00A0}\x{200B}\x{FEFF}]/u', ' ', $email);
        $email = preg_replace('/\s+/u', '', $email);
        return trim($email);
    }

    private function normalizeName(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = strip_tags($name);
        $name = preg_replace('/[\x{00A0}\x{200B}\x{FEFF}]/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim($name);
    }

    private function sanitizePhone(string $phone): ?string
    {
        $phone = html_entity_decode($phone, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $phone = strip_tags($phone);
        $phone = preg_replace('/[^0-9+\-\(\)\.\/ ]+/', '', $phone);
        $phone = preg_replace('/\s+/u', ' ', $phone);
        $phone = trim($phone);
        return $phone === '' ? null : $phone;
    }


    public function updateCustomers()
    {
        $lastUpdate = $this->customerHelper->getLastCustomerUpdateSync();
        $numberOfCustomers = (int)$this->customerHelper->getNumberOfCustomers();
        $customers = $this->customerResourceCollectionFactory->create()
            ->addAttributeToSelect('ac_contact_id')
            ->addAttributeToSelect('ac_customer_id')
            ->addAttributeToFilter('ac_contact_id', ['neq' => null])
            ->addAttributeToFilter(self::AC_SYNC_STATUS, ['eq' => CronConfig::SYNCED])
            ->addAttributeToFilter('updated_at', ['gt' => $lastUpdate])
            ->setOrder('updated_at', 'asc')
            ->setPageSize($numberOfCustomers);
        foreach ($customers as $customer) {
            $this->updateCustomer($customer);
            $lastUpdate = $customer->getUpdatedAt();
        }
        if (isset($lastUpdate)) {
            $this->customerHelper->setLastCustomerUpdateSync($lastUpdate);
            $this->cacheTypeList->cleanType('config');
        }
    }

    public function createGuestContact($data)
    {
        $acContact = null;
        $email = $this->normalizeEmail((string)($data['email'] ?? ''));
        $firstName = $this->normalizeName((string)($data['firstName'] ?? ''));
        $lastName = $this->normalizeName((string)($data['lastName'] ?? ''));
        $data['email'] = $email;
        $data['firstName'] = $firstName;
        $data['lastName'] = $lastName;
        if (isset($data['phone'])) {
            $data['phone'] = $this->sanitizePhone((string)$data['phone']);
        }
        $validationError = $this->getCustomerValidationError($email, $firstName, $lastName);
        if ($validationError !== null) {
            $this->logger->warning('MODULE: Customer guest contact sync skipped. ' . $validationError);
            return $acContact;
        }

        if ($email !== '') {
            $acContact = $this->searchContact($data['email']);
            if (!$acContact) {
                $contactData['contact'] = $data;
                $result = $this->curl->createContacts(self::METHOD, self::CONTACT_ENDPOINT, $contactData);
                if (!$result['success'] && $result['status'] == "404") {
                    $acContact = null;
                }
                if (!empty($result['data']['contact']['id'])) {
                    $acContact = $result['data']['contact']['id'];
                }
            }
        }
        return $acContact;
    }

    public function createGuestCustomer($data, $storeId)
    {
        $acCustomer = null;
        $acContact = null;

        $email = $this->normalizeEmail((string)($data['email'] ?? ''));
        $firstName = $this->normalizeName((string)($data['firstName'] ?? ''));
        $lastName = $this->normalizeName((string)($data['lastName'] ?? ''));
        $data['email'] = $email;
        $data['firstName'] = $firstName;
        $data['lastName'] = $lastName;
        if (isset($data['phone'])) {
            $data['phone'] = $this->sanitizePhone((string)$data['phone']);
        }
        $validationError = $this->getCustomerValidationError($email, $firstName, $lastName);
        if ($validationError !== null) {
            $this->logger->warning('MODULE: Customer guest ecommerce sync skipped. ' . $validationError);
            return ['ac_contact_id' => $acContact, 'ac_customer_id' => $acCustomer];
        }

        if ($email !== '') {
            $acContact = $this->createGuestContact($data);

            $acCustomer = $this->searchCustomer($data['email'], $this->coreHelper->getConnectionId($storeId));
            $ecomCustomerData=[];
            $data['connectionid'] = $this->coreHelper->getConnectionId($storeId);
            $data['externalid'] = $data['email'];
            $data['acceptsMarketing'] = (int)$this->subscriberFactory->create()->loadBySubscriberEmail($data['email'], $this->storeManager->getStore($storeId)->getWebsiteId())->isSubscribed();
            $ecomCustomerData['ecomCustomer'] = $data;
            if (!$acCustomer) {
                $result = $this->curl->createContacts(self::METHOD, self::ECOM_CUSTOMER_ENDPOINT, $ecomCustomerData);
            } else {
                $result = $this->curl->createContacts(self::METHOD_PUT, self::ECOM_CUSTOMER_ENDPOINT. '/' . $acCustomer, $ecomCustomerData);

            }
            if (!$result['success'] && $result['status'] == "404") {
                $acCustomer = null;
            }
            if ($result['success'] && isset($result['data']['ecomCustomer']['id'])) {
                $acCustomer = $result['data']['ecomCustomer']['id'];
            }
        }
        return ['ac_contact_id' => $acContact, 'ac_customer_id' => $acCustomer];
    }

    public function updateCustomer($customer)
    {
        $contactData = $this->contactBody($customer);
        $acContact = $customer->getAcContactId();
        $acCustomer = $customer->getAcCustomerId();
        $validationError = $this->getCustomerValidationError(
            (string)$contactData['contact']['email'],
            (string)$contactData['contact']['firstName'],
            (string)$contactData['contact']['lastName']
        );
        if ($validationError !== null) {
            $this->saveResult($customer->getId(), CronConfig::NOT_SYNCED, $acContact, $acCustomer);
            $this->logger->warning(
                'MODULE: Customer sync skipped for customer ID ' . $customer->getId() . '. ' . $validationError
            );
            return ['ac_contact_id' => $acContact, 'ac_customer_id' => $acCustomer];
        }
        try {
            if ($acContact) {
                $result = $this->curl->createContacts(self::METHOD_PUT, self::CONTACT_ENDPOINT . '/' . $acContact, $contactData);
                if (!$result['success'] && $result['status'] == "404") {
                    $acContact = null;
                }
            } else {
                $acContact = $this->searchContact($customer->getEmail());
                if (!$acContact) {
                    $result = $this->curl->createContacts(self::METHOD, self::CONTACT_ENDPOINT, $contactData);
                    $acContact = $result['data']['contact']['id'] ?? null;
                }
            }
            if ($acContact) {
                $customerData = $this->getEcomCustomerData($customer);
                if ($acCustomer) {
                    $result = $this->curl->createContacts(self::METHOD_PUT, self::ECOM_CUSTOMER_ENDPOINT . '/' . $customer->getAcCustomerId(), $customerData);
                    if (!$result['success'] && $result['status'] == "404") {
                        $acCustomer = null;
                    }
                } else {
                    $acCustomer = $this->searchCustomer($customer->getEmail(), $this->coreHelper->getConnectionId($customer->getStoreId()));
                    if (!$acCustomer) {
                        $result = $this->curl->createContacts(self::METHOD, self::ECOM_CUSTOMER_ENDPOINT, $customerData);
                        $acCustomer = $result['data']['ecomCustomer']['id'];
                        if (!$result['success'] && $result['status'] == "404") {
                            $acCustomer = null;
                        }
                        if ($result['success'] && isset($result['data']['ecomCustomer']['id'])) {
                            $acCustomer = $result['data']['ecomCustomer']['id'];
                        }
                    }

                }
                if ($acCustomer && $acContact) {
                    $this->saveResult($customer->getId(), CronConfig::SYNCED, $acContact, $acCustomer);
                } else {
                    $this->saveResult($customer->getId(), CronConfig::NOT_SYNCED, $acContact, $acCustomer);
                }
            } else {
                $this->saveResult($customer->getId(), CronConfig::NOT_SYNCED, $acContact, $acCustomer);
            }
        } catch (\Exception $e) {
            $this->saveResult($customer->getId(), CronConfig::NOT_SYNCED, $acContact, $acCustomer);
            $this->logger->critical("MODULE: Customer  contact/sync" . $e->getMessage());
        }
        return ['ac_contact_id' => $acContact, 'ac_customer_id' => $acCustomer];
    }

    public function searchCustomer($email, $connectionId)
    {
        $result = 0;
        $AcCustomer = $this->curl->listAllCustomers(
            self::GET_METHOD,
            self::ECOM_CUSTOMER_ENDPOINT,
            $email
        );
        $ecomCustomers = $AcCustomer['data']['ecomCustomers'] ?? [];
        foreach ($ecomCustomers as $Ac) {
            if (($Ac['connectionid'] ?? null) === $connectionId && !empty($Ac['id'])) {
                $result= $Ac['id'];
            }
        }
        return $result;
    }

    public function searchContact($email)
    {
        $result = 0;
        $AcCustomer = $this->curl->listAllCustomers(
            self::GET_METHOD,
            self::CONTACT_ENDPOINT,
            $email
        );
        $contacts = $AcCustomer['data']['contacts'] ?? [];
        if (($AcCustomer['status'] ?? null) == 200 && !empty($contacts[0]['id'])) {
            $result = $contacts[0]['id'];
        }
        return $result;
    }

    public function syncCustomers()
    {
        if ($this->customerHelper->isCustomerSyncingEnabled()) {
            $this->updateCustomers();
            $numberOfCustomers = (int)$this->customerHelper->getNumberOfCustomers();

            $customers = $this->customerResourceCollectionFactory->create()
                ->addAttributeToFilter(
                    [
                    ['attribute' => self::AC_SYNC_STATUS,'null' => true ],
                    ['attribute' => self::AC_SYNC_STATUS,'neq' => CronConfig::SYNCED ]
                    ]
                )
                ->setPageSize($numberOfCustomers);

            foreach ($customers as $customer) {
                try {
                    $this->updateCustomer($customer);
                } catch (\Exception $e) {
                    $this->logger->critical("MODULE: Customer " . $e->getMessage());
                }
            }
        }
    }
}
