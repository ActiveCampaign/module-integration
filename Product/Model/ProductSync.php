<?php
declare(strict_types=1);

namespace ActiveCampaign\Product\Model;

use ActiveCampaign\Core\Helper\Curl;
use ActiveCampaign\Core\Helper\Data as CoreData;

class ProductSync
{
    private $curl;
    private $coreData;

    public function __construct(
        Curl $curl,
        CoreData $coreData
    ) {
        $this->curl = $curl;
        $this->coreData = $coreData;
    }

    public function bulkUpsertProducts(array $products, ?int $legacyConnectionId = null): array
    {
        $connectionId = $legacyConnectionId ?: (int)($this->coreData->getConnectionId() ?? 0);
        $payloadProducts = [];

        foreach ($products as $product) {
            $item = $product;
            if (!isset($item['legacyConnectionId']) && $connectionId) {
                $item['legacyConnectionId'] = $connectionId;
            }
            $payloadProducts[] = $item;
        }

        $query = <<<'GQL'
mutation bulkUpsertProducts($products: [ProductInput!]!) {
  bulkUpsertProducts(products: $products) {
    id
    storePrimaryId
    storeBaseProductId
    baseProductName
    baseProductDescription
    baseProductStoreCreatedDate
    baseProductStoreModifiedDate
    tags
    categories
    baseProductUrl
    baseProductUrlSlug
    baseProductWeight
    variantSku
    variantName
    variantDescription
    variantPriceCurrency
    variantPriceAmount
    variantStoreCreatedDate
    variantStoreModifiedDate
    variantUrl
    variantUrlSlug
    variantWeight
    legacyConnectionId
    isVisible
  }
}
GQL;

        $batchSize = 100;
        $responses = [];
        $errors = [];
        $status = 200;
        for ($i = 0; $i < count($payloadProducts); $i += $batchSize) {
            $slice = array_slice($payloadProducts, $i, $batchSize);
            $res = $this->curl->graphql(
                $query,
                ['products' => $slice],
                'bulkUpsertProducts'
            );
            $responses[] = $res;
            $status = $res['status'] ?? $status;
            if (!empty($res['data']['errors'])) {
                $errors = array_merge($errors, $res['data']['errors']);
            } elseif (!empty($res['errors'])) {
                $errors = array_merge($errors, $res['errors']);
            }
        }

        if (!empty($errors)) {
            $messages = [];
            foreach ($errors as $err) {
                if (is_array($err) && isset($err['message'])) {
                    $messages[] = (string)$err['message'];
                } elseif (is_string($err)) {
                    $messages[] = $err;
                }
            }
            return [
                'success' => false,
                'status' => $status,
                'message' => 'GraphQL validation or connection errors: ' . implode(' | ', array_unique($messages)),
                'data' => ['errors' => $errors]
            ];
        }

        return [
            'success' => true,
            'status' => $status,
            'data' => $responses
        ];
    }
}
