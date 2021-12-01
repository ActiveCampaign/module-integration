<?php

namespace ActiveCampaign\AbandonedCart\Model;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\AbandonedCart\Helper\Data as AbandonedCartHelper;
use ActiveCampaign\Core\Helper\Data as CoreHelper;
use ActiveCampaign\AbandonedCart\Model\Config\CronConfig;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerResourceCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Psr\Log\LoggerInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteResourceCollectionFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory as QuoteItemCollectionFactory;
use Magento\Quote\Model\QuoteFactory as QuoteFactory;
use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Zend\Log\Writer\Stream;
use Zend\Log\Logger;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterface;

class AbandonedCartSendData extends \Magento\Framework\Model\AbstractModel
{
    public const AC_SYNC_STATUS = "ac_sync_status";

    public const ABANDONED_CART_URL_ENDPOINT = "ecomOrders";

    public const CONTACT_ENDPOINT = "contact/sync";

    public const ECOM_CUSTOMER_ENDPOINT = "ecomCustomers";

    public const METHOD = "POST";

    public const UPDATE_METHOD = "PUT";

    public const GET_METHOD = "GET";

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
     * @var Curl
     */
    protected $curl;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $imageHelperFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterfaceFactory
     */
    protected $_productRepositoryFactory;

    /**
     * @var AppEmulation
     */
    protected $appEmulation;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * CustomerSync constructor.
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     * @param CustomerResourceCollectionFactory $customerResourceCollectionFactory
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param Attribute $eavAttribute
     * @param AbandonedCartHelper $abandonedCartHelper
     * @param QuoteResourceCollectionFactory $quoteResourceCollectionFactory
     * @param Curl $curl
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param CoreHelper $coreHelper
     * @param QuoteItemCollectionFactory $quoteItemCollectionFactory
     * @param \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory
     * @param \Magento\Catalog\Helper\ImageFactory $imageHelperFactory
     * @param QuoteFactory $quoteFactory
     * @param AppEmulation $appEmulation
     * @param StoreManagerInterface $storeManager
     * @param CustomerModel $customerModel
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository,
        CustomerResourceCollectionFactory $customerResourceCollectionFactory,
        CustomerFactory $customerFactory,
        CustomerResource $customerResource,
        Attribute $eavAttribute,
        AbandonedCartHelper $abandonedCartHelper,
        QuoteResourceCollectionFactory $quoteResourceCollectionFactory,
        Curl $curl,
        LoggerInterface $logger,
        CartRepositoryInterface $cartRepositoryInterface,
        CoreHelper $coreHelper,
        QuoteItemCollectionFactory $quoteItemCollectionFactory,
        ProductRepositoryInterfaceFactory $productRepositoryFactory,
        ImageFactory $imageHelperFactory,
        QuoteFactory $quoteFactory,
        AppEmulation $appEmulation,
        StoreManagerInterface $storeManager,
        CustomerModel $customerModel
    ) {
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
        $this->customerResourceCollectionFactory = $customerResourceCollectionFactory;
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->eavAttribute = $eavAttribute;
        $this->abandonedCartHelper = $abandonedCartHelper;
        $this->quoteResourceCollectionFactory = $quoteResourceCollectionFactory;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->coreHelper = $coreHelper;
        $this->quoteItemCollectionFactory = $quoteItemCollectionFactory;
        $this->_productRepositoryFactory = $productRepositoryFactory;
        $this->imageHelperFactory = $imageHelperFactory;
        $this->quoteFactory = $quoteFactory;
        $this->appEmulation = $appEmulation;
        $this->storeManager = $storeManager;
        $this->customerModel = $customerModel;
    }

    /**
     * @param $quoteId
     * @return array
     */
    public function sendAbandonedCartData($quoteId = null)
    {
        $quoteItemsData = [];
        $contact = [];
        $result = [];
        $numberOfAbandonedCart = (int)$this->abandonedCartHelper->getNumberOfAbandonedCart();
        $abandonedCarts = $this->quoteResourceCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('main_table.' . self::AC_SYNC_STATUS, [
                ['neq' => CronConfig::SYNCED]
            ])
            ->addFieldToFilter(
                'main_table.is_active',
                '1'
            );
        if ($quoteId) {
            $abandonedCarts->addFieldToFilter(
                'main_table.entity_id',
                ['in' => $quoteId]
            );
        }

        $abandonedCarts->setPageSize($numberOfAbandonedCart);
        foreach ($abandonedCarts as $abandonedCart) {
            $connectionId = $this->coreHelper->getConnectionId($abandonedCart->getStoreId());
            $customerId = $abandonedCart->getCustomerId();

            $customerAcId = 0;
            $quote = $this->quoteFactory->create()->load($abandonedCart->getEntityId());
            if ($customerId) {
                $this->createEcomCustomer($abandonedCart->getCustomerId(), $quote);
                $customerEmail = $abandonedCart->getCustomerEmail();
                $customerModel = $this->customerFactory->create();
                $this->customerResource->load($customerModel, $customerId);
                if ($customerModel->getAcCustomerId()) {
                    $customerAcId = $customerModel->getAcCustomerId();
                }
            } else {
                $customerEmail = $quote->getBillingAddress()->getEmail();
                if (!$customerEmail) {
                    $result['error'] = __('Customer Email does not exist.');
                    return $result;
                }
                $websiteId  = $this->storeManager->getWebsite()->getWebsiteId();
                $customerModel = $this->customerModel;
                $customerModel->setWebsiteId($websiteId);
                $customerModel->loadByEmail($customerEmail);
                if ($customerModel->getId()) {
                    $customerId = $customerModel->getId();
                } else {
                    $customerId = 0;
                }
                $this->createEcomCustomer($customerId, $quote);
                $customerModel = $this->customerFactory->create();
                $this->customerResource->load($customerModel, $customerId);
                if ($customerModel->getAcCustomerId()) {
                    $customerAcId = $customerModel->getAcCustomerId();
                } else {
                    if ($quote->getAcTempCustomerId()) {
                        $customerAcId = $quote->getAcTempCustomerId();
                    } else {
                        $AcCustomer = $this->curl->listAllCustomers(
                            self::GET_METHOD,
                            self::ECOM_CUSTOMER_ENDPOINT,
                            $customerEmail
                        );
                        foreach ($AcCustomer['data']['ecomCustomers'] as $Ac) {
                            if ($Ac['connectionid'] == $connectionId) {
                                $customerAcId = $Ac['id'];
                            }
                        }
                    }
                }
            }

            $quoteItems = $this->getQuoteItems($abandonedCart->getEntityId());
            foreach ($quoteItems as $quoteItem) {
                $this->appEmulation->startEnvironmentEmulation(
                    $abandonedCart->getStoreId(),
                    \Magento\Framework\App\Area::AREA_FRONTEND,
                    true
                );
                $product = $this->_productRepositoryFactory->create()
                            ->get($quoteItem->getSku());
                $imageUrl = $this->imageHelperFactory->create()
                            ->init($product, 'product_thumbnail_image')->getUrl();
                $this->appEmulation->stopEnvironmentEmulation();
                $quoteItemsData[] = [
                    "externalid" => $quoteItem->getItemId(),
                    "name" => $quoteItem->getName(),
                    "price" => $this->coreHelper->priceToCents($quoteItem->getPrice()),
                    "quantity" => $quoteItem->getQty(),
                    "sku" => $quoteItem->getSku(),
                    "description" => $quoteItem->getDescription(),
                    "imageUrl" => $imageUrl,
                    "productUrl" => $product->getProductUrl()
                ];
            }

            $abandonedCartData = [
                "ecomOrder" => [
                    "externalcheckoutid" => $abandonedCart->getEntityId(),
                    "source" => 1,
                    "email" => $customerEmail,
                    "orderProducts" => $quoteItemsData,
                    "orderDiscounts" => [
                        "discountAmount" => $this->coreHelper->priceToCents($abandonedCart->getDiscountAmount())
                    ],
                    "abandonedDate" => $abandonedCart->getCreatedAt(),
                    "externalCreatedDate" => $abandonedCart->getCreatedAt(),
                    "externalUpdatedDate" => $abandonedCart->getUpdatedAt(),
                    "shippingMethod" => $abandonedCart->getShippingMethod(),
                    "totalPrice" => $this->coreHelper->priceToCents($abandonedCart->getGrandTotal()),
                    "shippingAmount" => $this->coreHelper->priceToCents($abandonedCart->getShippingAmount()),
                    "taxAmount" => $this->coreHelper->priceToCents($abandonedCart->getTaxAmount()),
                    "discountAmount" => $this->coreHelper->priceToCents($abandonedCart->getDiscountAmount()),
                    "currency" => $abandonedCart->getGlobalCurrencyCode(),
                    "orderNumber" => $abandonedCart->getEntityId(),
                    "connectionid" => $connectionId,
                    "customerid" => ($customerAcId === null) ? 0 : $customerAcId,
                ]
            ];

            try {
                if (!empty($abandonedCart->getAcOrderSyncId())) {
                    $abandonedCartResult = $this->curl->sendRequestAbandonedCart(
                        self::UPDATE_METHOD,
                        self::ABANDONED_CART_URL_ENDPOINT . "/" . (int)$abandonedCart->getAcOrderSyncId(),
                        $abandonedCartData
                    );
                } else {
                    $abandonedCartResult = $this->curl->sendRequestAbandonedCart(
                        self::METHOD,
                        self::ABANDONED_CART_URL_ENDPOINT,
                        $abandonedCartData
                    );
                }

                if ($abandonedCartResult['success'] == 1) {
                    $syncStatus = CronConfig::SYNCED;
                } else {
                    $syncStatus = CronConfig::FAIL_SYNCED;
                }

                if(isset($abandonedCartResult['data']['ecomOrder']['id'])) {
                    $acOrderId = $abandonedCartResult['data']['ecomOrder']['id'];
                    $this->saveResult($abandonedCart->getEntityId(), $acOrderId, $syncStatus);
                }

                if (isset($abandonedCartResult['success']) && $abandonedCartResult['success'] == 1) {
                    $result['success'] = __("Abandoned cart data successfully synced!!");
                }elseif (isset($abandonedCartResult['message'])) {
                    $result['error'] = $abandonedCartResult['message'];
                }
            } catch (\Exception $e) {
                $result['error'] = __($e->getMessage());
                $this->logger->critical($e);
            }
        }
        return $result;
    }

    /**
     * @param $quoteId
     * @return array
     */
    private function getQuoteItems($quoteId)
    {
        $quoteItemCollection = $this->quoteItemCollectionFactory->create();
        $quoteItem           = $quoteItemCollection
            ->addFieldToSelect('*')
            ->addFieldToFilter('quote_id', [$quoteId]);
        return $quoteItem;
    }

    /**
     * @param $quoteId
     * @param $acOrderId
     * @param $syncStatus
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    private function saveResult($quoteId, $acOrderId, $syncStatus)
    {
        $quoteModel = $this->cartRepositoryInterface->get($quoteId);
        if ($quoteModel->getEntityId()) {
            $quoteModel->setAcOrderSyncId($acOrderId);
            $quoteModel->setAcSyncStatus($syncStatus);
        }
        $quoteModel->save();
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
                $attributeId = $this->eavAttribute->getIdByCode(CustomerModel::ENTITY, $attribute->getAttributeCode());
                $attributeValues['field'] = $attributeId;
                $attributeValues['value'] = $attribute->getValue();
                $fieldValues[] = $attributeValues;
            }
        }
        return $fieldValues;
    }

    /**
     * @param $customerId
     * @return array
     */
    private function getCustomer($customerId)
    {
        $customerModel = $this->customerFactory->create();
        $this->customerResource->load($customerModel, $customerId);
        return $customerModel;
    }

    /**
     * @param $customerId
     * @return array
     */
    private function createEcomCustomer($customerId, $quote)
    {

        $ecomCustomerArray = [];
        $ecomCustomerId = 0;
        $syncStatus = CronConfig::NOT_SYNCED;
        $customer = $this->getCustomer($customerId);
        if ($customerId) {
            $customerId = $customer->getId();
            $contact['email'] = $customer->getEmail();
            $customerEmail = $customer->getEmail();
            $contact['firstName'] = $customer->getFirstname();
            $contact['lastName'] = $customer->getLastname();
            $contact['phone'] = $this->getTelephone($customer->getDefaultBilling());
            $contact['fieldValues'] = $this->getFieldValues($customerId);
        } else {
            $customerId = 0;
            $contact['email'] = $quote->getBillingAddress()->getEmail();
            $customerEmail = $quote->getBillingAddress()->getEmail();
            $contact['firstName'] = $quote->getBillingAddress()->getFirstname();
            $contact['lastName'] = $quote->getBillingAddress()->getLastname();
            $contact['phone'] = $quote->getBillingAddress()->getTelephone();
            $contact['fieldValues'] = [];
        }
        $contactData['contact'] = $contact;

        try {
            $contactResult = $this->curl->createContacts(self::METHOD, self::CONTACT_ENDPOINT, $contactData);
            $contactId = isset($contactResult['data']['contact']['id']) ? $contactResult['data']['contact']['id'] : null;
            $connectionid = $this->coreHelper->getConnectionId($customer->getStoreId());

            if (isset($contactResult['data']['contact']['id'])) {
                if (!$customer->getAcCustomerId()) {
                    $ecomCustomer['connectionid'] = $connectionid;
                    $ecomCustomer['externalid'] = $customerId;
                    $ecomCustomer['email'] = $customerEmail;
                    $ecomCustomerData['ecomCustomer'] = $ecomCustomer;
                    $AcCustomer = $this->curl->listAllCustomers(
                        self::GET_METHOD,
                        self::ECOM_CUSTOMER_ENDPOINT,
                        $customerEmail
                    );
                    if (isset($AcCustomer['data']['ecomCustomers'][0])) {
                        foreach ($AcCustomer['data']['ecomCustomers'] as $Ac) {
                            if ($Ac['connectionid'] == $connectionid) {
                                $ecomCustomerId = $Ac['id'];
                            }
                        }
                    }
                    if (!$ecomCustomerId) {
                        $ecomCustomerResult = $this->curl->createContacts(
                            self::METHOD,
                            self::ECOM_CUSTOMER_ENDPOINT,
                            $ecomCustomerData
                        );
                        $ecomCustomerId = isset($ecomCustomerResult['data']['ecomCustomer']['id']) ? $ecomCustomerResult['data']['ecomCustomer']['id'] : null;
                    }
                } else {
                    $ecomCustomerId = $customer->getAcCustomerId();
                }
            }

            if ($ecomCustomerId !=  0) {
                $syncStatus = CronConfig::SYNCED;
            } else {
                $syncStatus = CronConfig::FAIL_SYNCED;
            }

            if ($customerId) {
                $this->saveCustomerResult($customerId, $syncStatus, $contactId, $ecomCustomerId);
            } else {
                $this->saveCustomerResultQuote($quote, $ecomCustomerId);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * @param $customerId
     * @param $syncStatus
     * @param $contactId
     * @param $ecomCustomerId
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    private function saveCustomerResult($customerId, $syncStatus, $contactId, $ecomCustomerId)
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
     * @param $quote
     * @param $ecomCustomerId
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    private function saveCustomerResultQuote($quote, $ecomCustomerId)
    {
        if ($ecomCustomerId) {
            $quote->setData("ac_temp_customer_id", $ecomCustomerId);
            $quote->save();
        }
    }
}
