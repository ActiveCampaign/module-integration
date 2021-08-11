<?php
namespace ActiveCampaign\Core\Helper;

use ActiveCampaign\Core\Helper\Data as ActiveCampaignHelper;
use ActiveCampaign\Core\Logger\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use ActiveCampaign\SyncLog\Model\SyncLog;

class Curl extends AbstractHelper
{
    const API_VERSION = "/api/3/";
    const HTTP_VERSION = "1.1";
    const CONTENT_TYPE = "application/json";

    /**
     * @var Client|ClientInterface
     */
    private $client;

    /**
     * @var JsonHelper
     */
    private $jsonHelper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ActiveCampaignHelper
     */
    private $activeCampaignHelper;

    /**
     * @var SyncLog
     */
    private $syncLog;

    /**
     * Curl constructor.
     * @param Context $context
     * @param ClientInterface|null $client
     * @param JsonHelper $jsonHelper
     * @param Logger $logger
     * @param Data $activeCampaignHelper
     * @param SyncLog $syncLog
     */
    public function __construct(
        Context $context,
        ClientInterface $client = null,
        JsonHelper $jsonHelper,
        Logger $logger,
        ActiveCampaignHelper $activeCampaignHelper,
        SyncLog $syncLog
    ) {
        $this->client = $client ?: new Client();
        $this->jsonHelper = $jsonHelper;
        $this->logger = $logger;
        $this->activeCampaignHelper = $activeCampaignHelper;
        $this->syncLog = $syncLog;
        parent::__construct($context);
    }

    /**
     * @param $method
     * @param $urlEndpoint
     * @param $request
     * @param array $data
     * @return array
     */
    public function createConnection($method, $urlEndpoint, $request, $data = [])
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiUrl = empty($apiUrl) ? $request['api_url'] : $apiUrl;

        $apiKey = $this->activeCampaignHelper->getApiKey();
        $apiKey = empty($apiKey) ? $request['api_key'] : $apiKey;

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = $this->jsonHelper->jsonEncode($data);
        $headers = $this->getHeaders($apiKey);

        $result = $this->sendRequest($urlEndpoint, $method, $url, $headers, $bodyData);
        return $result;
    }

    /**
     * @param $method
     * @param $urlEndpoint
     * @param array $data
     * @param $request
     * @return array
     */
    public function orderDataSend($method, $urlEndpoint, $data = [])
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();
        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = $this->jsonHelper->jsonEncode($data);
        $headers = $this->getHeaders($apiKey);
        $result = $this->sendRequest($urlEndpoint, $method, $url, $headers, $bodyData);
        return $result;
    }

    /**
     * @param $method
     * @param $urlEndpoint
     * @param array $data
     * @return array
     */
    public function orderDataUpdate($method, $urlEndpoint, $data = [])
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();
        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = $this->jsonHelper->jsonEncode($data);
        $headers = $this->getHeaders($apiKey);
        $result = $this->sendRequest($urlEndpoint, $method, $url, $headers, $bodyData);
        return $result;
    }

    /**
     * @param $method
     * @param $urlEndpoint
     * @param $orderId
     * @return array
     */
    public function orderDataDelete($method, $urlEndpoint, $orderId)
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();
        $url = $apiUrl . self::API_VERSION . $urlEndpoint . $orderId;
        $headers = $this->getHeaders($apiKey);
        $result = $this->sendRequest($urlEndpoint, $method, $url, $headers);
        return $result;
    }

    /**
     * @param $method
     * @param $urlEndpoint
     * @return array
     */
    public function deleteConnection($method, $urlEndpoint)
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();
        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $headers = $this->getHeaders($apiKey);

        $result = $this->sendRequest($urlEndpoint, $method, $url, $headers);
        return $result;
    }

    public function createContacts($method, $urlEndpoint, $data = [])
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $bodyData = $this->jsonHelper->jsonEncode($data);
        $headers = $this->getHeaders($apiKey);

        $result = $this->sendRequest($urlEndpoint, $method, $url, $headers, $bodyData);
        return $result;
    }

    public function getAllConnections($method, $urlEndpoint)
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;
        $headers = $this->getHeaders($apiKey);

        $result = $this->sendRequest($urlEndpoint, $method, $url, $headers);
        return $result;
    }

    /**
     * @param $apiKey
     * @return array
     */
    private function getHeaders($apiKey)
    {
        $headers = [];
        $headers['Content-Type'] = self::CONTENT_TYPE;
        $headers['Api-Token'] = $apiKey;
        return $headers;
    }

    /**
     * @param $method
     * @param $url
     * @param $headers
     * @param $bodyData
     * @return array
     */
    private function sendRequest($urlEndpoint, $method, $url, $headers, $bodyData = '')
    {
        $result = [];
        $synclog = $this->syncLog;
        try {
            $request = [
                "METHOD"=>$method,
                "URL"=>$url,
                "HTTP VERSION" => self::HTTP_VERSION,
                "HEADERS"=>$headers,
                "BODY DATA"=>$bodyData
            ];
            $synclog->setType($urlEndpoint);
            $synclog->setEndpoint($urlEndpoint);
            $synclog->setMethod($method);
            $synclog->setRequest($this->jsonHelper->jsonEncode($request));
            $this->logger->info("REQUEST", $request);

            $options = [];
            $options[RequestOptions::HEADERS] = $headers;
            if ($bodyData !== null) {
                $options[RequestOptions::BODY] = $bodyData;
            }

            $resultCurl = $this->client->request($method, $url, $options);

            $body = $resultCurl->getBody()->getContents();
            $response = $this->jsonHelper->jsonDecode($body);
            $this->logger->info("RESPONSE", $response);
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
            $this->logger->critical($e);
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }
        $synclog->save();
        $synclog->unsetData();
        return $result;
    }

    /**
     * @return string[]
     */
    private function successCodes()
    {
        return [
            200 => "OK",
            201 => "Created"
        ];
    }

    /**
     * @return string[]
     */
    private function failureCodes()
    {
        return [
            400 => "Bad Request",
            404 => "Not Found",
            422 => "Unprocessable Entity"
        ];
    }

    /**
     * @param $response
     * @return \Magento\Framework\Phrase
     */
    private function getMessage($response)
    {
        if (isset($response['message'])) {
            return $response['message'];
        } elseif (isset($response['error']['title'])) {
            return $response['error']['title'];
        } elseif (isset($response['errors']['0']['title'])) {
            return $response['errors']['0']['title'];
        }

        return __("Something was wrong Please try again later");
    }

    public function sendRequestAbandonedCart($method, $urlEndpoint, $data = [])
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint;

        $bodyData = $this->jsonHelper->jsonEncode($data);

        $headers = $this->getHeaders($apiKey);
        $type = 'ecomAbandonedCarts';
        $result = $this->sendRequest($type, $method, $url, $headers, $bodyData);
        return $result;
    }

    public function listAllCustomers($method, $urlEndpoint, $customerEmail)
    {
        $apiUrl = $this->activeCampaignHelper->getApiUrl();
        $apiKey = $this->activeCampaignHelper->getApiKey();

        $url = $apiUrl . self::API_VERSION . $urlEndpoint . '?filters[email]=' . urlencode($customerEmail);

        $headers = $this->getHeaders($apiKey);
        $type = 'ecomCustomers';
        $result = $this->sendRequest($type, $method, $url, $headers);
        return $result;
    }
}
