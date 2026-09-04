<?php
namespace ActiveCampaign\Order\Model\OrderData;

use ActiveCampaign\Order\Model\Config\CronConfig;
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
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface as StoreManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use ActiveCampaign\Customer\Model\Customer;

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
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var CustomerModel
     */
    protected $customerModel;

    /**
     * @var CustomerModel
     */
    protected $coreHelper;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * OrderDataSend constructor.
     *
     * @param ProductRepositoryInterfaceFactory $productRepositoryFactory
     * @param ImageFactory                      $imageHelperFactory
     * @param ActiveCampaignOrderHelper         $activeCampaignOrderHelper
     * @param CoreHelper                        $activeCampaignHelper
     * @param ConfigInterface                   $configInterface
     * @param Curl                              $curl
     * @param CustomerRepositoryInterface       $customerRepositoryInterface
     * @param StoreRepositoryInterface          $storeRepository
     * @param CustomerFactory                   $customerFactory
     * @param StoreManagerInterface             $storeManager
     * @param CustomerModel                     $customerModel
     * @param AddressRepositoryInterface        $addressRepository
     * @param Attribute                         $eavAttribute
     * @param CoreHelper                        $coreHelper
     * @param CustomerResource                  $customerResource
     * @param CartRepositoryInterface           $quoteRepository
     * @param Customer                          $customer
     * @param TimezoneInterface                 $dateTime
     * @param ResourceConnection                $resourceConnection
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
        CustomerResource $customerResource,
        CartRepositoryInterface $quoteRepository,
        Customer $customer,
        TimezoneInterface $dateTime,
        ResourceConnection $resourceConnection
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
        $this->quoteRepository = $quoteRepository;
        $this->customer =  $customer;
        $this->dateTime = $dateTime;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param  $order
     * @return array
     * @throws GuzzleException
     */
    public function orderDataSend($order): array
    {
        $return = [];
        $isEnabled = $this->activeCampaignOrderHelper->isOrderSyncEnabled();
        if ($isEnabled) {
            if ($order->getStatus() === 'canceled') {
                return $this->sendCancelledOrder($order);
            }
            try {
                $connectionId = $this->activeCampaignHelper->getConnectionId($order->getStoreId());
                $customerId = $order->getCustomerId();
                $quoteModel = null;
                try {
                    $quoteModel = $this->quoteRepository->get($order->getQuoteId());
                    $quote = $quoteModel;
                } catch (\Exception $e) {
                    $quote = $order;
                }

                if ($customerId) {
                    $AcCustomer = $this->customer->updateCustomer($this->getCustomer($customerId));
                } else {
                    $customerEmail = $quote->getBillingAddress()->getEmail();
                    $contact['email'] = $customerEmail;
                    $contact['firstName'] = $quote->getBillingAddress()->getFirstname();
                    $contact['lastName'] = $quote->getBillingAddress()->getLastname();
                    $contact['phone'] = $quote->getBillingAddress()->getTelephone();
                    $contact['fieldValues'] = [];
                    $AcCustomer = $this->customer->createGuestCustomer($contact, $order->getStoreId());
                }
                $customerAcId = $AcCustomer['ac_customer_id'];
                if ($quoteModel) {
                    $this->saveCustomerResultQuote($quote, $customerAcId);
                }
                $timezone = $this->dateTime->getConfigTimezone(\Magento\Store\Model\ScopeInterface::SCOPE_STORES, $order->getStoreId());
                $items = [];
                foreach ($order->getAllVisibleItems() as $item) {
                    $product = $this->resolveProduct($item, (int)$order->getStoreId());

                    $imageUrl = $this->imageUrl($item, $product, $order->getStoreId());
                    $categoriesName = $this->getProductCategoriesName($product);

                    $items[] = [
                                "externalid" => $item->getProductId(),
                                "name" => $item->getName(),
                                "price" => $this->activeCampaignHelper->priceToCents($item->getPrice()),
                                "quantity" => $item->getQtyOrdered(),
                                "category" => $categoriesName,
                                "sku" => $item->getSku(),
                                "description" => $item->getDescription(),
                                "imageUrl" => $imageUrl,
                                "productUrl" => $product ? (string)$product->getProductUrl() : ''
                            ];
                }
                $data = [
                            "ecomOrder" => [
                                "externalid" => $order->getId(),
                                "source" => 1,
                                "email" => $order->getCustomerEmail(),
                                "orderProducts" => $items,
                                "orderDiscounts" => [
                                    "discountAmount" => $this->activeCampaignHelper->priceToCents($order->getDiscountAmount())
                                ],
                                "externalCreatedDate" => $this->dateTime->date(strtotime($order->getCreatedAt()), null, $timezone)->format('Y-m-d\TH:i:sP'),
                                "externalUpdatedDate" => $this->dateTime->date(strtotime($order->getUpdatedAt()), null, $timezone)->format('Y-m-d\TH:i:sP'),
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
                    $AcOrderId=0;
                    if ($quoteModel) {
                        $AcOrderId = $quote->getAcOrderSyncId();
                    }
                    if ($AcOrderId > 0) {
                        $result = $this->curl->orderDataSend(
                            self::UPDATE_METHOD,
                            self::URL_ENDPOINT . '/' . (int) $AcOrderId,
                            $data
                        );
                    } else {
                        $result = $this->curl->orderDataSend(
                            self::METHOD,
                            self::URL_ENDPOINT,
                            $data
                        );
                    }
                    $resultStatus = (string)($result['status'] ?? '');
                    if ($resultStatus === '422' || $resultStatus === '400') {
                        $acOrderId = $this->fetchExistingOrderId($order, $quote);
                        if ($acOrderId) {
                            $updateResult = $this->curl->orderDataSend(
                                self::UPDATE_METHOD,
                                self::URL_ENDPOINT . '/' . (int)$acOrderId,
                                $data
                            );
                            if (!empty($updateResult)) {
                                $result = $updateResult;
                            }
                        }
                    } else {
                        $acOrderId = isset($result['data']['ecomOrder']['id']) ? $result['data']['ecomOrder']['id'] : null;
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

    private function sendCancelledOrder($order): array
    {
        $return = [];

        try {
            $connectionId = (int)$this->activeCampaignHelper->getConnectionId($order->getStoreId());
            if ($connectionId <= 0) {
                throw new LocalizedException(__('Invalid ActiveCampaign connection ID.'));
            }

            $storeId = (int)$order->getStoreId();
            $timezone = $this->dateTime->getConfigTimezone(
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $storeId
            );
            $createdAt = $this->dateTime
                ->date(strtotime($order->getCreatedAt()), null, $timezone)
                ->format('Y-m-d\TH:i:sP');
            $updatedAt = $this->dateTime
                ->date(strtotime($order->getUpdatedAt()), null, $timezone)
                ->format('Y-m-d\TH:i:sP');

            $customerId = $order->getCustomerId();
            try {
                $quoteModel = $this->quoteRepository->get($order->getQuoteId());
                $quote = $quoteModel;
            } catch (\Exception $e) {
                $quote = $order;
            }

            $billingAddress = method_exists($quote, 'getBillingAddress')
                ? $quote->getBillingAddress()
                : (method_exists($order, 'getBillingAddress') ? $order->getBillingAddress() : null);
            $shippingAddress = method_exists($quote, 'getShippingAddress')
                ? $quote->getShippingAddress()
                : (method_exists($order, 'getShippingAddress') ? $order->getShippingAddress() : null);

            if ($customerId) {
                try {
                    $AcCustomer = $this->customer->updateCustomer($this->getCustomer($customerId));
                } catch (\Exception $e) {
                    $AcCustomer = ['ac_customer_id' => 0];
                }
            } else {
                $contact = [
                    'email' => $billingAddress ? (string)$billingAddress->getEmail() : (string)$order->getCustomerEmail(),
                    'firstName' => $billingAddress ? (string)$billingAddress->getFirstname() : '',
                    'lastName' => $billingAddress ? (string)$billingAddress->getLastname() : '',
                    'phone' => $billingAddress ? (string)$billingAddress->getTelephone() : '',
                    'fieldValues' => []
                ];
                try {
                    $AcCustomer = $this->customer->createGuestCustomer($contact, $storeId);
                } catch (\Exception $e) {
                    $AcCustomer = ['ac_customer_id' => 0];
                }
            }

            $email = (string)($billingAddress ? $billingAddress->getEmail() : $order->getCustomerEmail());
            $firstName = (string)($billingAddress ? $billingAddress->getFirstname() : '');
            $lastName = (string)($billingAddress ? $billingAddress->getLastname() : '');
            $phone = (string)($billingAddress ? $billingAddress->getTelephone() : '');
            $company = (string)($billingAddress && method_exists($billingAddress, 'getCompany') ? $billingAddress->getCompany() : '');

            $lineItems = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $product = $this->resolveProduct($item, $storeId);
                $productId = $product ? (int)$product->getId() : (int)$item->getProductId();
                $baseProductId = $productId;
                if ($product && method_exists($product, 'getData')) {
                    $baseId = $product->getData('row_id');
                    if ($baseId === null) {
                        $baseId = $product->getData('entity_id');
                    }
                    if ($baseId !== null) {
                        $baseProductId = (int)$baseId;
                    }
                }
                $priceCents = $this->activeCampaignHelper->priceToCents((float)$item->getPrice());
                $row = [
                    'name' => (string)$item->getName(),
                    'quantity' => (int)$item->getQtyOrdered(),
                    'priceAmount' => round($priceCents / 100, 4),
                    'productStorePrimaryId' => (string)$productId,
                    'storeBaseProductId' => (string)$baseProductId
                ];
                $lineItems[] = $row;
            }

            $grandTotalCents = $this->activeCampaignHelper->priceToCents((float)$order->getGrandTotal());
            $shippingCents = $this->activeCampaignHelper->priceToCents((float)$order->getShippingAmount());
            $discountCents = $this->activeCampaignHelper->priceToCents((float)abs($order->getDiscountAmount()));

            $query = <<<'GQL'
mutation upsertOrder($order: OrderInput!) {
  upsertOrder(order: $order) {
    storeOrderId
    connectionId
    normalizedStatus
  }
}
GQL;

            $orderInput = [
                'email' => $email,
                'legacyConnectionId' => $connectionId,
                'storeOrderId' => (string)$order->getId(),
                'storeCustomerId' => $customerId ? (string)$customerId : null,
                'orderNumber' => (string)$order->getIncrementId(),
                'cartId' => $order->getQuoteId() ? (string)$order->getQuoteId() : null,
                'storeExternalOrderId' => (string)$order->getId(),
                'creationSource' => $this->buildCreationSource($storeId),
                'storeCreatedDate' => $createdAt,
                'storeModifiedDate' => $updatedAt,
                'storeStatus' => (string)$order->getStatus(),
                'normalizedStatus' => 'CANCELLED',
                'shippingMethod' => $order->getShippingMethod() ? (string)$order->getShippingMethod() : null,
                'orderUrl' => $this->buildOrderUrl($order, $storeId),
                'isTestOrder' => false,
                'createdByRecurringPayment' => false,
                'acceptsMarketing' => false,
                'customerLocale' => $this->resolveStoreLocale($storeId),
                'salesChannel' => 'magento',
                'currency' => (string)$order->getOrderCurrencyCode(),
                'finalAmount' => round($grandTotalCents / 100, 4),
                'shippingAmount' => round($shippingCents / 100, 4),
                'discountsAmount' => round($discountCents / 100, 4),
                'paymentMethod' => $order->getPayment() && method_exists($order->getPayment(), 'getMethod')
                    ? (string)$order->getPayment()->getMethod()
                    : null
            ];
            $orderInput = array_filter($orderInput, static function ($v) {
                return $v !== null && $v !== '';
            });

            if (!empty($lineItems)) {
                $orderInput['lineItems'] = $lineItems;
            }

            $billingAddressInput = $this->buildGraphQlAddress($billingAddress, $email);
            if ($billingAddressInput !== null) {
                $orderInput['billingAddress'] = $billingAddressInput;
            }
            $shippingEmail = $shippingAddress && method_exists($shippingAddress, 'getEmail')
                ? (string)$shippingAddress->getEmail()
                : $email;
            $shippingAddressInput = $this->buildGraphQlAddress($shippingAddress, $shippingEmail);
            if ($shippingAddressInput !== null) {
                $orderInput['shippingAddress'] = $shippingAddressInput;
            }

            $customerData = array_filter([
                'firstName' => $firstName,
                'lastName' => $lastName,
                'phone' => $phone,
                'company' => $company
            ], static function ($v) {
                return $v !== null && $v !== '';
            });
            if (!empty($customerData)) {
                $orderInput['customerData'] = $customerData;
            }

            $discountAmountRaw = (float)$order->getDiscountAmount();
            if ($discountAmountRaw < 0) {
                $discounts = [[
                    'name' => (string)($order->getDiscountDescription() ?: __('Discount')),
                    'type' => 'CART',
                    'discountAmount' => round($discountCents / 100, 4)
                ]];
                $orderInput['discounts'] = $discounts;
            }

            $notes = [];
            $statusHistory = $order->getStatusHistoryCollection();
            if ($statusHistory) {
                foreach ($statusHistory as $history) {
                    $comment = $history->getComment();
                    if ($comment !== null && $comment !== '') {
                        $notes[] = (string)$comment;
                    }
                }
            }
            if (!empty($notes)) {
                $orderInput['notes'] = array_slice($notes, 0, 20);
            }

            $variables = ['order' => $orderInput];
            $result = $this->curl->graphql($query, $variables, 'upsertOrder');

            $hasGraphQlErrors = !empty($result['data']['errors']);
            $isSuccess = !empty($result['success']) && !$hasGraphQlErrors;

            $syncStatus = $isSuccess ? CronConfig::SYNCED : CronConfig::NOT_SYNCED;

            $this->persistOrderSyncStatus((int)$order->getId(), $syncStatus);
            $order->setData('ac_order_sync_status', $syncStatus);

            if ($isSuccess) {
                $return['success'] = __('Order cancellation successfully synced.');
            } else {
                $return['success'] = false;
                if ($hasGraphQlErrors) {
                    $messages = [];
                    foreach ((array)$result['data']['errors'] as $err) {
                        if (is_array($err) && isset($err['message'])) {
                            $messages[] = (string)$err['message'];
                        }
                    }
                    if (empty($messages) && isset($result['errorMessage'])) {
                        $messages[] = (string)$result['errorMessage'];
                    }
                    $return['errorMessage'] = empty($messages)
                        ? __('Order cancellation sync failed.')
                        : __('GraphQL errors: %1', implode(' | ', array_unique($messages)));
                } elseif (isset($result['errorMessage'])) {
                    $return['errorMessage'] = __($result['errorMessage']);
                } else {
                    $return['errorMessage'] = __('Order cancellation sync failed.');
                }
            }
        } catch (\Exception $e) {
            $return['success'] = false;
            $return['errorMessage'] = __($e->getMessage());
        }

        return $return;
    }

    /**
     * @param mixed $address
     * @param string $defaultEmail
     * @return array|null
     */
    private function buildGraphQlAddress($address, string $defaultEmail = ''): ?array
    {
        if ($address === null || !is_object($address)) {
            return null;
        }

        $firstName = method_exists($address, 'getFirstname') ? (string)$address->getFirstname() : '';
        $lastName = method_exists($address, 'getLastname') ? (string)$address->getLastname() : '';
        if ($firstName === '' && $lastName === '') {
            return null;
        }

        $company = method_exists($address, 'getCompany') ? (string)$address->getCompany() : '';
        $street = method_exists($address, 'getStreet') ? $address->getStreet() : [];
        $streetArr = is_array($street) ? array_values($street) : [$street];
        $address1 = isset($streetArr[0]) ? (string)$streetArr[0] : '';
        $address2 = isset($streetArr[1]) ? (string)$streetArr[1] : '';
        $address3 = isset($streetArr[2]) ? (string)$streetArr[2] : '';
        $city = method_exists($address, 'getCity') ? (string)$address->getCity() : '';
        $province = method_exists($address, 'getRegionCode')
            ? (string)$address->getRegionCode()
            : (method_exists($address, 'getRegion') ? (string)$address->getRegion() : '');
        $postal = method_exists($address, 'getPostcode') ? (string)$address->getPostcode() : '';
        $country = method_exists($address, 'getCountryId') ? (string)$address->getCountryId() : '';
        $phone = method_exists($address, 'getTelephone') ? (string)$address->getTelephone() : '';
        $email = method_exists($address, 'getEmail') ? (string)$address->getEmail() : $defaultEmail;

        return array_filter([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'company' => $company,
            'address1' => $address1,
            'address2' => $address2,
            'address3' => $address3,
            'city' => $city,
            'province' => $province,
            'postal' => $postal,
            'country' => $country,
            'phone' => $phone,
            'email' => $email
        ], static function ($v) {
            return $v !== null && $v !== '';
        });
    }

    /**
     * @param mixed $order
     * @param int $storeId
     * @return string|null
     */
    private function buildOrderUrl($order, int $storeId): ?string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $baseUrl = rtrim((string)$store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB), '/');
            $orderId = $order ? (string)$order->getId() : '';
            if ($baseUrl === '' || $orderId === '') {
                return null;
            }
            return $baseUrl . '/sales/order/view/order_id/' . $orderId . '/';
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param int $storeId
     * @return string|null
     */
    private function resolveStoreLocale(int $storeId): ?string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $locale = (string)$store->getConfig('general/locale/code');
            return $locale !== '' ? $locale : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param int $storeId
     * @return string
     */
    private function buildCreationSource(int $storeId): string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            $storeName = trim((string)$store->getName());
            return 'Magento' . ($storeName !== '' ? ' ' . $storeName : '');
        } catch (\Exception $e) {
            return 'Magento';
        }
    }

    private function formatIso8601(?string $dateTime): string
    {
        if (!$dateTime) {
            return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c');
        }
        return (new \DateTimeImmutable($dateTime, new \DateTimeZone('UTC')))->format('c');
    }

    private function persistOrderSyncStatus(int $orderId, int $syncStatus): void
    {
        if ($orderId <= 0) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');
        $salesOrderGridTable = $this->resourceConnection->getTableName('sales_order_grid');

        $bind = ['ac_order_sync_status' => $syncStatus];
        $where = ['entity_id = ?' => $orderId];

        $connection->update($salesOrderTable, $bind, $where);
        $connection->update($salesOrderGridTable, $bind, $where);
    }

    public function imageUrl($item, $product, $storeId)
    {
        if (!$product) {
            return '';
        }

        $imageUrl = $this->imageHelperFactory->create()
            ->init($product, 'product_page_image_medium')->getUrl();
        if (str_contains($imageUrl, 'images/product/placeholder')) {

            $store = $this->storeManager->getStore($storeId);
            $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';

            if ($product->getMediaGalleryImages() && count($product->getMediaGalleryImages()->getItems()) > 0) {
                $imageUrl = $baseUrl . $product->getImage();
            } elseif (($item->getProductType() !== 'simple') && $item->getProductOptionByCode('super_product_config') && (isset($item->getProductOptionByCode('super_product_config')['product_id']))) {
                try {
                    $product = $this->_productRepositoryFactory->create()
                        ->getById($item->getProductOptionByCode('super_product_config')['product_id'], false, $storeId);
                    $imageUrl = $baseUrl . $product->getImage();
                } catch (\Exception $e) {
                    return '';
                }
            }
        }
        return $imageUrl;
    }

    private function resolveProduct($item, int $storeId)
    {
        $product = $item->getProduct();
        if ($product) {
            return $product;
        }

        if (!$item->getProductId()) {
            return null;
        }

        try {
            return $this->_productRepositoryFactory->create()->getById((int)$item->getProductId(), false, $storeId);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getProductCategoriesName($product): string
    {
        if (!$product) {
            return '';
        }

        $categories = $product->getCategoryCollection()->addAttributeToSelect('name');
        $categoriesName = [];
        foreach ($categories as $category) {
            $categoriesName[] = $category->getName();
        }

        return implode(', ', $categoriesName);
    }

    private function fetchExistingOrderId($order, $quote)
    {
        $externalId = rawurlencode((string)$order->getId());
        $connectionId = rawurlencode((string)$this->activeCampaignHelper->getConnectionId($order->getStoreId()));

        $response = $this->curl->orderDataSend(
            self::GET_METHOD,
            self::URL_ENDPOINT . '?filters[externalid]=' . $externalId . '&filters[connectionid]=' . $connectionId
        );

        return $this->findExistingOrderId($response, $order, $quote);
    }

    private function findExistingOrderId(array $response, $order, $quote)
    {
        $ecomOrders = $response['data']['ecomOrders'] ?? [];
        if (!is_array($ecomOrders)) {
            return null;
        }

        $billingAddress = method_exists($quote, 'getBillingAddress') ? $quote->getBillingAddress() : null;
        $lookupEmail = $billingAddress ? (string)$billingAddress->getEmail() : (string)$order->getCustomerEmail();
        $lookupExternalId = (string)$order->getId();

        foreach ($ecomOrders as $ecomOrder) {
            if (!is_array($ecomOrder) || empty($ecomOrder['id'])) {
                continue;
            }

            if (isset($ecomOrder['externalid']) && (string)$ecomOrder['externalid'] === $lookupExternalId) {
                return $ecomOrder['id'];
            }

            if ($lookupEmail !== '' && isset($ecomOrder['email']) && (string)$ecomOrder['email'] === $lookupEmail) {
                return $ecomOrder['id'];
            }
        }

        return null;
    }
    /**
     * @param  $customerId
     * @return object
     */
    private function getCustomer($customerId): object
    {
        $customerModel = $this->customerFactory->create();
        $this->customerResource->load($customerModel, $customerId);
        return $customerModel;
    }


    /**
     * @param  $quote
     * @param  $ecomCustomerId
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
