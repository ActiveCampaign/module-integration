<?php

namespace ActiveCampaign\AbandonedCart\Model;

use ActiveCampaign\AbandonedCart\Helper\Data as AbandonedCartHelper;
use ActiveCampaign\AbandonedCart\Model\Config\CronConfig;
use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Core\Helper\Data as CoreHelper;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerResourceCollectionFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteResourceCollectionFactory;
use Magento\Quote\Model\ResourceModel\Quote\Item\Collection;
use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory as QuoteItemCollectionFactory;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use ActiveCampaign\Customer\Model\Customer;

class AbandonedCartSendData extends AbstractModel
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
     * @var ImageFactory
     */
    protected $imageHelperFactory;

    /**
     * @var ProductRepositoryInterfaceFactory
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
     * @var DateTime
     */
    private $dateTime;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    private $customerId;


    /**
     * AbandonedCartSendData constructor.
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
     * @param ProductRepositoryInterfaceFactory $productRepositoryFactory
     * @param ImageFactory $imageHelperFactory
     * @param QuoteFactory $quoteFactory
     * @param AppEmulation $appEmulation
     * @param StoreManagerInterface $storeManager
     * @param CustomerModel $customerModel
     * @param TimezoneInterface $dateTime
     * @param CartRepositoryInterface $quoteRepository
     * @param UrlInterface $urlBuilder
     * @param Customer $customer
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
        CustomerModel $customerModel,
        TimezoneInterface $dateTime,
        CartRepositoryInterface $quoteRepository,
        UrlInterface $urlBuilder,
        Customer $customer
    )
    {
        $this->urlBuilder = $urlBuilder;
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
        $this->dateTime = $dateTime;
        $this->quoteRepository = $quoteRepository;
        $this->customer = $customer;
    }

    /**
     * @param $quoteId
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     * @throws GuzzleException|GuzzleException
     */
    public function sendAbandonedCartData($quoteId = null): array
    {
        $result = [];
        $numberOfAbandonedCart = (int)$this->abandonedCartHelper->getNumberOfAbandonedCart();
        $abandonedCarts = $this->quoteResourceCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('ac_synced_date', [
                ['lt' => new \Zend_Db_Expr('main_table.updated_at')],
                ['null' => true]
            ])
            ->addFieldToFilter(
                'is_active',
                '1'
            )
            ->addFieldToFilter(
                'items_count',
                ['gt' => 0]
            );

        if ($quoteId) {
            $abandonedCarts->addFieldToFilter('entity_id', ['eq' => $quoteId]);
        }
        $abandonedCarts->setPageSize($numberOfAbandonedCart);
        $abandonedCarts->getSelect()->join(array('address' => $abandonedCarts->getResource()->getTable('quote_address')), 'main_table.entity_id = address.quote_id')
            ->where("address.address_type='billing' and (main_table.customer_email is not null or  address.email is not null)");
        foreach ($abandonedCarts as $abandonedCart) {

            $connectionId = $this->coreHelper->getConnectionId($abandonedCart->getStoreId());

            $quote = $this->quoteRepository->get($abandonedCart->getEntityId());
            $AcCustomer = NULL;
            if ($this->isGuest($quote) || ($abandonedCart->getCustomerId() && !$this->getCustomer($abandonedCart->getCustomerId())->getCustomerId())) {
                $customerEmail = $quote->getBillingAddress()->getEmail();
                if (!$customerEmail) {
                    $result['error'] = __('Customer Email does not exist.');
                    continue;
                }
                $contact['email'] = $quote->getBillingAddress()->getEmail();
                $contact['firstName'] = $quote->getBillingAddress()->getFirstname();
                $contact['lastName'] = $quote->getBillingAddress()->getLastname();
                $contact['phone'] = $quote->getBillingAddress()->getTelephone();
                $contact['fieldValues'] = [];
                $AcCustomer = $this->customer->createGuestCustomer($contact,$quote->getStoreId());
            } else {
                $AcCustomer = $this->customer->updateCustomer($this->getCustomer($abandonedCart->getCustomerId()));
            }
            $this->customerId = $AcCustomer['ac_customer_id'];
            $this->saveCustomerResultQuote($quote,$this->customerId);

            $abandonedCart->collectTotals();
            $quoteItemsData = $this->getQuoteItemsData($abandonedCart->getEntityId(), $abandonedCart->getStoreId());
            $abandonedCartRepository = $this->quoteRepository->get($abandonedCart->getId());
            $abandonedCartData = [
                "ecomOrder" => [
                    "externalcheckoutid" => $abandonedCart->getEntityId(),
                    "source" => 1,
                    "email" => $quote->getBillingAddress()->getEmail(),
                    "orderProducts" => $quoteItemsData,
                    "orderDiscounts" => [
                        "discountAmount" => $this->coreHelper->priceToCents($abandonedCart->getDiscountAmount())
                    ],
                    "orderUrl" => $this->urlBuilder->getDirectUrl('checkout/cart'),
                    "abandonedDate" => $this->dateTime->date(strtotime($abandonedCartRepository->getUpdatedAt()))->format('Y-m-d\TH:i:sP'),
                    "externalCreatedDate" => $this->dateTime->date(strtotime($abandonedCartRepository->getCreatedAt()))->format('Y-m-d\TH:i:sP'),
                    "externalUpdatedDate" => $this->dateTime->date(strtotime($abandonedCartRepository->getUpdatedAt()))->format('Y-m-d\TH:i:sP'),
                    "shippingMethod" => $abandonedCart->getShippingAddress()->getShippingMethod(),
                    "totalPrice" => $this->coreHelper->priceToCents($abandonedCart->getGrandTotal()),
                    "shippingAmount" => $this->coreHelper->priceToCents($abandonedCart->getShippingAmount()),
                    "taxAmount" => $this->coreHelper->priceToCents($abandonedCart->getTaxAmount()),
                    "discountAmount" => $this->coreHelper->priceToCents($abandonedCart->getDiscountAmount()),
                    "currency" => $abandonedCart->getGlobalCurrencyCode(),
                    "orderNumber" => $abandonedCart->getEntityId(),
                    "connectionid" => $connectionId,
                    "customerid" => $this->customerId,
                ]
            ];

            try {
                if (is_null($abandonedCart->getAcSyncedDate())) {
                    $abandonedCartResult = $this->curl->sendRequestAbandonedCart(
                        self::METHOD,
                        self::ABANDONED_CART_URL_ENDPOINT,
                        $abandonedCartData
                    );
                } else {
                    $abandonedCartResult = $this->curl->sendRequestAbandonedCart(
                        self::UPDATE_METHOD,
                        self::ABANDONED_CART_URL_ENDPOINT . "/" . (int)$abandonedCart->getAcOrderSyncId(),
                        $abandonedCartData
                    );
                }

                if ($abandonedCartResult['success']) {
                    $syncStatus = CronConfig::SYNCED;
                } else {
                    if(!$abandonedCartResult['success'] && $abandonedCartResult['status'] == "404"){
                        $abandonedCartResult = $this->curl->sendRequestAbandonedCart(
                            self::UPDATE_METHOD,
                            self::ABANDONED_CART_URL_ENDPOINT . "/" . (int)$abandonedCart->getAcOrderSyncId(),
                            $abandonedCartData
                        );
                    }
                    if ($abandonedCartResult['success']) {
                        $syncStatus = CronConfig::SYNCED;
                    }else{
                        $syncStatus = CronConfig::FAIL_SYNCED;
                    }
                }

                if (isset($abandonedCartResult['data']['ecomOrder']['id'])) {
                    $acOrderId = $abandonedCartResult['data']['ecomOrder']['id'];
                    $this->saveResult($abandonedCart->getEntityId(), $acOrderId, $syncStatus);
                }

                if (isset($abandonedCartResult['success']) && $abandonedCartResult['success']) {
                    $result['success'] = __("Abandoned cart data successfully synced!!");
                } elseif (isset($abandonedCartResult['message'])) {
                    $result['error'] = $abandonedCartResult['message'];
                }
            } catch (\Exception $e) {
                $result['error'] = __($e->getMessage());
                $this->logger->critical("MODULE AbandonedCart: " . $e->getMessage());
            } catch (GuzzleException $e) {
                $this->logger->critical("MODULE AbandonedCart GuzzleException: " . $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * @throws NoSuchEntityException
     */
    private function getQuoteItemsData($entityId, $storeId): array
    {
        $quoteItemsData = [];
        $quoteItems = $this->getQuoteItems($entityId);
        foreach ($quoteItems as $quoteItem) {
            $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

            $product = $this->_productRepositoryFactory->create()
                ->getById($quoteItem->getProductId());

            $imageUrl = $this->imageHelperFactory->create()
                ->init($product, 'product_thumbnail_image')->getUrl();
            $this->appEmulation->stopEnvironmentEmulation();
            $quoteItemsData[] = [
                "externalid" => $quoteItem->getItemId(),
                "name" => $quoteItem->getName(),
                "price" => $this->coreHelper->priceToCents($quoteItem->getPriceInclTax()),
                "quantity" => $quoteItem->getQty(),
                "sku" => $quoteItem->getSku(),
                "description" => $quoteItem->getDescription(),
                "imageUrl" => $imageUrl,
                "productUrl" => $product->getProductUrl()
            ];
        }
        return $quoteItemsData;
    }

    /**
     * @param $quoteId
     * @return Collection
     */
    private function getQuoteItems($quoteId): Collection
    {
        $quoteItemCollection = $this->quoteItemCollectionFactory->create();
        return $quoteItemCollection
            ->addFieldToSelect('*')
            ->addFieldToFilter('quote_id', [$quoteId])
            ->addFieldToFilter('parent_item_id', ['null' => true]);
    }

    /**
     * @param $quoteId
     * @param $acOrderId
     * @param $syncStatus
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    private function saveResult($quoteId, $acOrderId, $syncStatus): void
    {
        $quoteModel = $this->cartRepositoryInterface->get($quoteId);
        if ($quoteModel->getEntityId()) {
            $quoteModel->setAcOrderSyncId($acOrderId);
            $quoteModel->setAcSyncStatus($syncStatus);
            if(!$quoteModel->getUpdatedAt()){
                $quoteModel->setAcSyncedDate($this->dateTime->date()->modify('+1 day')->format('Y-m-d H:i:s'));
            }else{
                $quoteModel->setAcSyncedDate($this->dateTime->date($quoteModel->getUpdatedAt())->modify('+1 day')->format('Y-m-d H:i:s'));
            }
        }
        $quoteModel->save();
    }

    /**
     * @param null $billingId
     * @return string|null
     * @throws LocalizedException
     */
    private function getTelephone($billingId = null): ?string
    {
        if ($billingId) {
            return $this->addressRepository->getById($billingId)->getTelephone();
        }
        return null;
    }


    public function isGuest($quote): bool
    {
        return is_null($quote->getCustomerId());
    }

    /**
     * @param $quote
     * @param $ecomCustomerId
     * @throws AlreadyExistsException
     */
    private function saveCustomerResultQuote($quote, $ecomCustomerId): void
    {
        if ($ecomCustomerId) {
            $quote->setData("ac_temp_customer_id", $ecomCustomerId);
            $quote->save();
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
