<?php

namespace ActiveCampaign\Customer\Controller\Adminhtml\Customer;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Customer\Model\Config\CronConfig;
use ActiveCampaign\Customer\Model\Customer;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\Adminhtml\Index\AbstractMassAction;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

class MassSync extends AbstractMassAction implements HttpPostActionInterface
{
    const CONTACT_ENDPOINT = "contact/sync";

    const METHOD = "POST";

    const ECOM_CUSTOMER_ENDPOINT = "ecomCustomers";

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Customer
     */
    private $customer;

    /**
     * @var Curl
     */
    protected $curl;

    protected $logger;
    /**
     * MassSync constructor.
     *
     * @param Context                     $context
     * @param Filter                      $filter
     * @param CollectionFactory           $collectionFactory
     * @param Customer                    $customer
     * @param Curl                        $curl
     * @param LoggerInterface             $logger
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        Customer $customer,
        Curl $curl,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context, $filter, $collectionFactory);
        $this->customer = $customer;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param  AbstractCollection $collection
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function massAction(AbstractCollection $collection)
    {
        $customersSynced = 0;
        $contactId = 0;
        $ecomCustomerId = 0;
        $syncStatus = CronConfig::NOT_SYNCED;

        foreach ($collection->getAllIds() as $customerId) {
            if (!empty($customerId)) {
                try {
                    $customer = $this->customer->getCustomerById($customerId);
                    $this->customer->updateCustomer($customer);
                } catch (\Exception $exception) {
                    $this->logger->critical("MODULE: Customer " . $exception);
                }
            }
            $customersSynced++;
        }

        if ($customersSynced) {
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) were synced in the ActiveCampaign.', $customersSynced));
        }
        /**
 * @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect
*/
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('customer/index');

        return $resultRedirect;
    }
}
