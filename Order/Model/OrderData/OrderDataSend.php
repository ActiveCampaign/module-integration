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
    protected  $customerModel;

    /**
     * @var CustomerModel
     */
    protected  $coreHelper;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * OrderDataSend constructor.
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
     * @param CartRepositoryInterface $quoteRepository
     * @param Customer $customer
     * @param TimezoneInterface $dateTime
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
        TimezoneInterface $dateTime
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
                $quoteModel = null;
                try{
                $quoteModel = $this->quoteRepository->get($order->getQuoteId());
                    $quote = $quoteModel;
                }catch (\Exception $e){
                    $quote = $order;
                }

                if ($customerId) {
                    $AcCustomer = $this->customer->updateCustomer($this->getCustomer($customerId));
                }else{
                    $customerEmail = $quote->getBillingAddress()->getEmail();
                    $contact['email'] = $customerEmail;
                    $contact['firstName'] = $quote->getBillingAddress()->getFirstname();
                    $contact['lastName'] = $quote->getBillingAddress()->getLastname();
                    $contact['phone'] = $quote->getBillingAddress()->getTelephone();
                    $contact['fieldValues'] = [];
                    $AcCustomer = $this->customer->createGuestCustomer($contact,$order->getStoreId());
                }
                $customerAcId = $AcCustomer['ac_customer_id'];
                if($quoteModel) {
                    $this->saveCustomerResultQuote($quote, $customerAcId);
                }
                $timezone = $this->dateTime->getConfigTimezone(\Magento\Store\Model\ScopeInterface::SCOPE_STORES, $order->getStoreId());
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
                                "email" => $order->getCustomerEmail(),
                                "orderProducts" => $items,
                                "orderDiscounts" => [
                                    "discountAmount" => $this->activeCampaignHelper->priceToCents($order->getDiscountAmount())
                                ],
                                "externalCreatedDate" => $this->dateTime->date(strtotime($order->getCreatedAt()),NULL,$timezone)->format('Y-m-d\TH:i:sP'),
                                "externalUpdatedDate" => $this->dateTime->date(strtotime($order->getUpdatedAt()),NULL,$timezone)->format('Y-m-d\TH:i:sP'),
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
                    if($quoteModel){
                        $AcOrderId = $quote->getAcOrderSyncId();
                    }
                    if($AcOrderId > 0){
                        $result = $this->curl->orderDataSend(
                            self::UPDATE_METHOD,
                            self::URL_ENDPOINT . '/' . (int) $AcOrderId,
                            $data
                        );
                    }else{
                        $result = $this->curl->orderDataSend(
                            self::METHOD,
                            self::URL_ENDPOINT,
                            $data
                        );
                    }
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
