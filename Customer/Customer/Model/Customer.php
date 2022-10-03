<?php

namespace ActiveCampaign\Customer\Model;

use ActiveCampaign\Core\Helper\Data as CoreHelper;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;

class Customer
{
    const AC_CUSTOMER_ID = 'ac_customer_id';

    /**
     * @var bool
     */
    private $isEcomCustomer = false;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * @var Attribute
     */
    protected $eavAttribute;

    /**
     * @var CoreHelper
     */
    private $coreHelper;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerResource
     */
    protected $customerResource;

    /**
     * Customer constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     * @param Attribute $eavAttribute
     * @param CoreHelper $coreHelper
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        Attribute $eavAttribute,
        CoreHelper $coreHelper,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource
    ) {
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->eavAttribute = $eavAttribute;
        $this->coreHelper = $coreHelper;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
    }

    /**
     * @param $customerId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getContactData($customerId)
    {
        $contact = [];
        $customer = $this->customerRepository->getById($customerId);

        $contact['email'] = $customer->getEmail();
        $contact['firstName'] = $customer->getFirstname();
        $contact['lastName'] = $customer->getLastname();
        $contact['phone'] = $this->getTelephone($customer->getDefaultBilling());
        $contact['fieldValues'] = $this->getFieldValues($customer->getCustomAttributes());
        $contact['isEcomCustomer'] = $this->isEcomCustomer;
        $contactData['contact'] = $contact;

        return $contactData;
    }

    /**
     * @param $customerId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getEcomCustomerData($customerId)
    {
        $ecomCustomer = [];
        $customer = $this->customerRepository->getById($customerId);

        $ecomCustomer['connectionid'] = $this->coreHelper->getConnectionId($customer->getStoreId());
        $ecomCustomer['externalid'] = $customer->getId();
        $ecomCustomer['email'] = $customer->getEmail();
        $ecomCustomerData['ecomCustomer'] = $ecomCustomer;

        return $ecomCustomerData;
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
     * @param $customAttributes
     * @return array
     */
    private function getFieldValues($customAttributes)
    {
        $fieldValues = [];
        if (!empty($customAttributes)) {
            foreach ($customAttributes as $attribute) {
                $attributeId = $this->eavAttribute->getIdByCode(
                    \Magento\Customer\Model\Customer::ENTITY,
                    $attribute->getAttributeCode()
                );
                if ($attribute->getAttributeCode() === self::AC_CUSTOMER_ID) {
                    $this->isEcomCustomer = ($attribute->getValue()) ? true : false;
                }
                $attributeValues['field'] = $attributeId;
                $attributeValues['value'] = $attribute->getValue();
                $fieldValues[] = $attributeValues;
            }
        }
        return $fieldValues;
    }

    /**
     * @param $customerId
     * @param $syncStatus
     * @param $contactId
     * @param $ecomCustomerId
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
}
