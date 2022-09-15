<?php
declare(strict_types=1);

namespace ActiveCampaign\Core\Helper;

class Curl extends \Magento\Framework\App\Helper\AbstractHelper
{
    public const API_VERSION = '/api/3/';
    public const HTTP_VERSION = '1.1';
    public const CONTENT_TYPE = 'application/json';

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $jsonHelper;

    /**
     * @var \ActiveCampaign\Core\Logger\Logger
     */
    private $logger;

    /**
     * @var \ActiveCampaign\Core\Helper\Data
     */
    private $activeCampaignHelper;

    /**
     * @var \ActiveCampaign\SyncLog\Model\SyncLog
     */
    private $syncLog;

    /**
     * Construct
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Serialize\Serializer\Json $jsonHelper
     * @param \ActiveCampaign\Core\Logger\Logger $logger
     * @param \ActiveCampaign\Core\Helper\Data $activeCampaignHelper
     * @param \ActiveCampaign\SyncLog\Model\SyncLog $syncLog
     * @param \GuzzleHttp\ClientInterface|null $client
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Serialize\Serializer\Json $jsonHelper,
        \ActiveCampaign\Core\Logger\Logger $logger,
        \ActiveCampaign\Core\Helper\Data $activeCampaignHelper,
        \ActiveCampaign\SyncLog\Model\SyncLog $syncLog,
        \GuzzleHttp\ClientInterface $client = null
    ) {
        $this->client = $client ?: new \GuzzleHttp\Client();
        $this->jsonHelper = $jsonHelper;
        $this->logger = $logger;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->syncLog = $syncLog;

        parent::__construct($context);
    }

    /**
     * Create connection
     *
     * @param string $method
     * @param string $urlEndpoint
     * @param array $request
     * @param array $data
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createConnection(
        string $method,
        string $urlEndpoint,
        array $request,
        array $data = []
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiUrl = empty($apiUrl) ? $request['api_url'] : $apiUrl;

        $apiKey = $this->activeCampaignHelper->getApiKey();
        $apiKey = empty($apiKey) ? $request['api_key'] : $apiKey;

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = (!empty($data)) ? $this->jsonHelper->serialize($data) : '';
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest($urlEndpoint, $method, $url, $headers, $bodyData);
    }

    /**
     * Send order data
     *
     * @param string $method
     * @param string $urlEndpoint
     * @param array $data
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function orderDataSend(
        string $method,
        string $urlEndpoint,
        array $data = []
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = (!empty($data)) ? $this->jsonHelper->serialize($data) : '';
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest($urlEndpoint, $method, $url, $headers, $bodyData);
    }

    /**
     * Update order data
     *
     * @param string $method
     * @param string $urlEndpoint
     * @param array $data
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function orderDataUpdate(
        string $method,
        string $urlEndpoint,
        array $data = []
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = (!empty($data)) ? $this->jsonHelper->serialize($data) : '';
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest($urlEndpoint, $method, $url, $headers, $bodyData);
    }

    /**
     * Delete order data
     *
     * @param string $method
     * @param string $urlEndpoint
     * @param int|string $orderId
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function orderDataDelete(
        string $method,
        string $urlEndpoint,
        int|string $orderId
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint . $orderId;
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest($urlEndpoint, $method, $url, $headers);
    }

    /**
     * Delete connection
     *
     * @param string $method
     * @param string $urlEndpoint
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function deleteConnection(
        string $method,
        string $urlEndpoint
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest($urlEndpoint, $method, $url, $headers);
    }

    /**
     * Create contacts
     *
     * @param string $method
     * @param string $urlEndpoint
     * @param array $data
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createContacts(
        string $method,
        string $urlEndpoint,
        array $data = []
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = (!empty($data)) ? $this->jsonHelper->serialize($data) : '';
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest($urlEndpoint, $method, $url, $headers, $bodyData);
    }

    /**
     * Get all connections
     *
     * @param string $method
     * @param string $urlEndpoint
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAllConnections(
        string $method,
        string $urlEndpoint
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest($urlEndpoint, $method, $url, $headers);
    }

    /**
     * Send request for abandoned cart
     *
     * @param string $method
     * @param string $urlEndpoint
     * @param array $data
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendRequestAbandonedCart(
        string $method,
        string $urlEndpoint,
        array $data = []
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = (!empty($data)) ? $this->jsonHelper->serialize($data) : '';
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest('ecomAbandonedCarts', $method, $url, $headers, $bodyData);
    }

    /**
     * List all customers
     *
     * @param string $method
     * @param string $urlEndpoint
     * @param string $customerEmail
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function listAllCustomers(
        string $method,
        string $urlEndpoint,
        string $customerEmail
    ): array {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint . '?filters[email]=' . urlencode($customerEmail);
        $headers = $this->getHeaders($apiKey);

        return $this->sendRequest('ecomCustomers', $method, $url, $headers);
    }

    /**
     * Get headers
     *
     * @param string|null $apiKey
     *
     * @return array
     */
    private function getHeaders(?string $apiKey): array
    {
        return [
            'Content-Type'  => self::CONTENT_TYPE,
            'Api-Token'     => $apiKey
        ];
    }

    /**
     * Send request
     *
     * @param string $urlEndpoint
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string $bodyData
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function sendRequest(
        string $urlEndpoint,
        string $method,
        string $url,
        array $headers,
        string $bodyData = ''
    ): array {
        $result = [];
        $synclog = $this->syncLog;

        try {
            $request = [
                'METHOD'        => $method,
                'URL'           => $url,
                'HTTP VERSION'  => self::HTTP_VERSION,
                'HEADERS'       => $headers,
                'BODY DATA'     => $bodyData
            ];

            /**
             * @todo Create data interfaces to maintain integrity of data
             */
            $synclog->setType($urlEndpoint);
            $synclog->setEndpoint($urlEndpoint);
            $synclog->setMethod($method);
            $synclog->setRequest($this->jsonHelper->serialize($request));

            $this->logger->info('REQUEST', $request);

            $options = [];
            $options[\GuzzleHttp\RequestOptions::HEADERS] = $headers;

            if ($bodyData !== null) {
                $options[\GuzzleHttp\RequestOptions::BODY] = $bodyData;
            }

            $resultCurl = $this->client->request($method, $url, $options);

            $body = $resultCurl->getBody()->getContents();
            $response = $this->jsonHelper->unserialize($body);

            $this->logger->info('RESPONSE', $response);

            $synclog->setResponse($body);
            $synclog->setStatus(1);

            if (!empty($resultCurl)) {
                $result['status'] = $resultCurl->getStatusCode();
                if (isset($result['status']) && array_key_exists($result['status'], $this->successCodes())) {
                    $result['success'] = true;
                    $result['data'] = $response;
                } elseif (isset($result['status']) && array_key_exists($result['status'], $this->failureCodes())) {
                    $result['success'] = false;
                    $result['message'] = $this->getMessage($response);
                    $result['data'] = $response;
                } else {
                    $result['success'] = false;
                    $result['message'] = $this->getMessage($response);
                }
            } else {
                $result['success'] = false;
                $result['message'] = __('Cannot connect to active campaign server. Please try again later.');
            }
        } catch (\Exception $e) {
            $synclog->setStatus(0);
            $synclog->setErrors($e->getMessage());

            $this->logger->critical('MODULE Core: ' . $e->getMessage());

            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        /**
         * @todo Replace with repository service contract
         */
        $synclog->save();
        $synclog->unsetData();

        return $result;
    }

    /**
     * Success codes
     *
     * @return array
     */
    private function successCodes(): array
    {
        return [
            200 => 'OK',
            201 => 'Created'
        ];
    }

    /**
     * Failure codes
     *
     * @return array
     */
    private function failureCodes(): array
    {
        return [
            400 => 'Bad Request',
            404 => 'Not Found',
            422 => 'Unprocessable Entity'
        ];
    }

    /**
     * Get message
     *
     * @param mixed $response
     *
     * @return \Magento\Framework\Phrase|string
     */
    private function getMessage(mixed $response): \Magento\Framework\Phrase|string
    {
        if (is_array($response)) {
            if (isset($response['message'])) {
                return $response['message'];
            } elseif (isset($response['error']['title'])) {
                return $response['error']['title'];
            } elseif (isset($response['errors']['0']['title'])) {
                return $response['errors']['0']['title'];
            }
        }

        return __('An unknown error occurred. Please try again later');
    }
}
