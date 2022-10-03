<?php
namespace ActiveCampaign\Order\Model\OrderData;

use ActiveCampaign\AbandonedCart\Model\Config\CronConfig;
use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Core\Helper\Data as ActiveCampaignHelper;
use ActiveCampaign\Core\Helper\Data as CoreHelper;
use ActiveCampaign\Order\Helper\Data as ActiveCampaignOrderHelper;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterface;

class OrderDataSend
{
    const URL_ENDPOINT = "ecomOrders";
    const METHOD = "POST";
    const UPDATE_METHOD = "PUT";
    const GET_METHOD = "GET";
    const AC_SYNC_STATUS = "ac_sync_status";
    const CONTACT_ENDPOINT = "contact/sync";
    const ECOM_CUSTOMER_ENDPOINT = "ecomOrders";
    const ECOM_CUSTOMERLIST_ENDPOINT = "ecomCustomers";

    /**
     * @var ActiveCampaignOrderHelper
     */
    private $activeCampaignOrderHelper;

    /**
     * @var ActiveCampaignHelper
     */
    private $activeCampaignHelper;

    /**
     * @var ConfigInterface
     */
    private $configInterface;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterfaceFactory
     */
    protected $_productRepositoryFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $_customerRepositoryInterface;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $imageHelperFactory;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * @var Attribute
     */
    protected $eavAttribute;

    /**
     * @var CustomerResource
     */
    protected $customerResource;

    /**
     * Order Data send Construct
     *
     * @param ProductRepositoryInterfaceFactory $productRepositoryFactory
     * @param ImageFactory $imageHelperFactory
     * @param ActiveCampaignOrderHelper $activeCampaignOrderHelper
     * @param CoreHelper $activeCampaignHelper
     * @param ConfigInterface $configInterface
     * @param Curl $curl
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param StoreRepositoryInterface $storeRepository
     * @param CustomerFactory $customerFactory
     * @param StoreManagerInterface $storeManager
     * @param CustomerModel $customerModel
     * @param AddressRepositoryInterface $addressRepository
     * @param Attribute $eavAttribute
     * @param CoreHelper $coreHelper
     * @param CustomerResource $customerResource
     */
    public function __construct(
        ProductRepositoryInterfaceFactory $productRepositoryFactory,
        ImageFactory $imageHelperFactory,
        ActiveCampaignOrderHelper $activeCampaignOrderHelper,
        ActiveCampaignHelper $activeCampaignHelper,
        ConfigInterface $configInterface,
        Curl $curl,
        CustomerRepositoryInterface $customerRepositoryInterface,
        StoreRepositoryInterface $storeRepository,
        CustomerFactory $customerFactory,
        StoreManagerInterface $storeManager,
        CustomerModel $customerModel,
        AddressRepositoryInterface $addressRepository,
        Attribute $eavAttribute,
        CoreHelper $coreHelper,
        CustomerResource $customerResource
    ) {
        $this->_productRepositoryFactory = $productRepositoryFactory;
        $this->imageHelperFactory = $imageHelperFactory;
        $this->activeCampaignOrderHelper = $activeCampaignOrderHelper;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->configInterface = $configInterface;
        $this->curl = $curl;
        $this->_customerRepositoryInterface = $customerRepositoryInterface;
        $this->storeRepository = $storeRepository;
        $this->customerFactory = $customerFactory;
        $this->storeManager = $storeManager;
        $this->customerModel = $customerModel;
        $this->addressRepository = $addressRepository;
        $this->eavAttribute = $eavAttribute;
        $this->coreHelper = $coreHelper;
        $this->customerResource = $customerResource;
    }

    /**
     * @param $order
     * @return array
     * @throws GuzzleException
     */
    public function orderDataSend($order): array
    {
        $return = [];
        $isEnabled = $this->activeCampaignOrderHelper->isOrderSyncEnabled();
        if ($isEnabled) {
            try {
                $connectionId = $this->activeCampaignHelper->getConnectionId($order->getStoreId());
                $customerId = $order->getCustomerId();
                $customerAcId = 0;
                if ($customerId) {
                    $this->createEcomCustomer($order->getCustomerId(), $order);
                    $customerEmail = $order->getCustomerEmail();
                    $customerModel = $this->customerFactory->create();
                    $this->customerResource->load($customerModel, $customerId);
                    if ($customerModel->getAcCustomerId()) {
                        $customerAcId = $customerModel->getAcCustomerId();
                    }
                } else {
                    $customerEmail = $order->getBillingAddress()->getEmail();
                    $websiteId  = $this->storeManager->getWebsite()->getWebsiteId();
                    $customerModel = $this->customerModel;
                    $customerModel->setWebsiteId($websiteId);
                    $customerModel->loadByEmail($customerEmail);
                    if ($customerModel->getId()) {
                        $customerId = $customerModel->getId();
                    } else {
                        $customerId = 0;
                    }
                    $this->createEcomCustomer($customerId, $order);
                    $customerModel = $this->customerFactory->create();
                    $this->customerResource->load($customerModel, $customerId);
                    if ($customerModel->getAcCustomerId()) {
                        $customerAcId = $customerModel->getAcCustomerId();
                    } else {
                        if ($order->getAcTempCustomerId()) {
                            $customerAcId = $order->getAcTempCustomerId();
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

                foreach ($order->getAllVisibleItems() as $item) {
                    $product = $this->_productRepositoryFactory->create()
                                ->get($item->getSku());
                    $imageUrl = $this->imageHelperFactory->create()
                                ->init($product, 'product_thumbnail_image')->getUrl();
                    $items[] = [
                                "externalid" => $item->getProductId(),
                                "name" => $item->getName(),
                                "price" => $this->activeCampaignHelper->priceToCents($item->getPrice()),
                                "quantity" => $item->getQtyOrdered(),
                                "category" => implode(', ', $product->getCategoryIds()),
                                "sku" => $item->getSku(),
                                "description" => $item->getDescription(),
                                "imageUrl" => $imageUrl,
                                "productUrl" => $product->getProductUrl()
                            ];
                }
                $data = [
                            "ecomOrder" => [
                                "externalid" => $order->getId(),
                                "source" => 1,
                                "email" => $customerEmail,
                                "orderProducts" => $items,
                                "orderDiscounts" => [
                                    "discountAmount" => $this->activeCampaignHelper->priceToCents($order->getDiscountAmount())
                                ],
                                "externalCreatedDate" => $order->getCreatedAt(),
                                "externalUpdatedDate" => $order->getUpdatedAt(),
                                "shippingMethod" => $order->getShippingMethod(),
                                "totalPrice" => $this->activeCampaignHelper->priceToCents($order->getGrandTotal()),
                                "shippingAmount" => $this->activeCampaignHelper->priceToCents($order->getShippingAmount()),
                                "taxAmount" => $this->activeCampaignHelper->priceToCents($order->getTaxAmount()),
                                "discountAmount" => $this->activeCampaignHelper->priceToCents($order->getDiscountAmount()),
                                "currency" => $order->getOrderCurrencyCode(),
                                "orderNumber" => $order->getIncrementId(),
                                "connectionid" => $connectionId,
                                "customerid" => $customerAcId
                            ]
                        ];

                if (!$order->getAcOrderSyncId()) {
                    $result = $this->curl->orderDataSend(
                        self::METHOD,
                        self::URL_ENDPOINT,
                        $data
                    );
                    if ($result['status'] == '422' || $result['status'] == '400') {
                        $ecomAlreadyExistOrderData = [];
                        $ecomAlreadyExistOrderResult = $this->curl->createContacts(
                            self::GET_METHOD,
                            self::URL_ENDPOINT,
                            $ecomAlreadyExistOrderData
                        );
                        $ecomOrders = $ecomAlreadyExistOrderResult['data']['ecomOrders'];
                        foreach ($ecomOrders as $ecomKey => $customers) {
                            $ecomOrderArray[$ecomOrders[$ecomKey]['email']] = $ecomOrders[$ecomKey]['id'];
                        }
                        $acOrderId = $ecomOrderArray[$customerEmail];
                    } else {
                        $acOrderId = isset($result['data']['ecomOrders']['id']) ? $result['data']['ecomOrders']['id'] : null;
                    }
                } else {
                    $acOrderId = $order->getAcOrderSyncId();
                }

                if ($acOrderId !=  0) {
                    $syncStatus = CronConfig::SYNCED;
                } else {
                    $syncStatus = CronConfig::FAIL_SYNCED;
                }

                $order->setData("ac_order_sync_status", $syncStatus)
                        ->setData("ac_order_sync_id", $acOrderId)
                        ->save();

                if (isset($result['success'])) {
                    $return['success'] = __("Order data successfully synced!!");
                }
            } catch (\Exception $e) {
                $return['success'] = false;
                $return['errorMessage'] = __($e->getMessage());
            }
        }
        return $return;
    }

    /**
     * @param null $billingId
     * @return string|null
     * @throws LocalizedException
     */
    private function getTelephone($billingId = null): ?string
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
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getFieldValues($customerId)
    {
        $fieldValues = [];
        $customAttributes = $this->_customerRepositoryInterface->getById($customerId);
        $customAttributes->getCustomAttributes();
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
     * @return object
     */
    private function getCustomer($customerId): object
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
        $ecomOrderArray = [];
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
                        self::ECOM_CUSTOMERLIST_ENDPOINT,
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
                            self::ECOM_CUSTOMERLIST_ENDPOINT,
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
            $this->logger->critical("MODULE Order " . $e);
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
