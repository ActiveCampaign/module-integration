<?php

namespace ActiveCampaign\Customer\Observer;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Customer\Model\Config\CronConfig;
use ActiveCampaign\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class CustomerSaveAfter implements ObserverInterface
{
    const CONTACT_ENDPOINT = "contact/sync";

    const METHOD = "POST";

    const ECOM_CUSTOMER_ENDPOINT = "ecomCustomers";

    /**
     * @var Customer
     */
    private $customer;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * CustomerSaveAfter constructor.
     * @param Customer $customer
     * @param Curl $curl
     * @param LoggerInterface $logger
     */
    public function __construct(
        Customer $customer,
        Curl $curl,
        LoggerInterface $logger
    ) {
        $this->customer = $customer;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    /**
     * Upgrade order customer email when customer has changed email
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Customer $originalCustomer */
        $originalCustomer = $observer->getEvent()->getOrigCustomerDataObject();
        if (!$originalCustomer) {
            return;
        }

        /** @var Customer $customer */
        $customer = $observer->getEvent()->getCustomerDataObject();

        $contactId = 0;
        $ecomCustomerId = 0;
        $syncStatus = CronConfig::NOT_SYNCED;
        $customerId = $customer->getId();

        try {
            $contactData = $this->customer->getContactData($customerId);
            $isEcomCustomer = $contactData['contact']['isEcomCustomer'];

            unset($contactData['contact']['isEcomCustomer']);

            $contactResult = $this->curl->createContacts(self::METHOD, self::CONTACT_ENDPOINT, $contactData);
            $contactId = isset($contactResult['data']['contact']['id']) ? $contactResult['data']['contact']['id'] : null;
            $syncStatus = ($contactResult['success']) ? CronConfig::SYNCED : CronConfig::FAIL_SYNCED;

            if ($contactResult['success'] && !$isEcomCustomer) {
                $ecomCustomerData = $this->customer->getEcomCustomerData($customerId);
                $ecomCustomerResult = $this->curl->createContacts(
                    self::METHOD,
                    self::ECOM_CUSTOMER_ENDPOINT,
                    $ecomCustomerData
                );
                $ecomCustomerId = isset($ecomCustomerResult['data']['ecomCustomer']['id']) ? $ecomCustomerResult['data']['ecomCustomer']['id'] : null;
            }
            $this->customer->saveResult($customerId, $syncStatus, $contactId, $ecomCustomerId);
        } catch (\Exception $e) {
            $this->logger->critical("MODULE: Customer " . $e);
        }
    }
}
