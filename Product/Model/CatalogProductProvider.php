<?php
declare(strict_types=1);

namespace ActiveCampaign\Product\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Media\Config as MediaConfig;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableType;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;
use Magento\Catalog\Api\ProductRepositoryInterface;

class CatalogProductProvider
{
    private $productCollectionFactory;
    private $storeManager;
    private $mediaConfig;
    private $scopeConfig;
    private $stockRegistry;
    private $configurableType;
    private $groupedType;
    private $productRepository;

    public function __construct(
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        MediaConfig $mediaConfig,
        ScopeConfigInterface $scopeConfig,
        StockRegistryInterface $stockRegistry,
        ConfigurableType $configurableType,
        GroupedType $groupedType,
        ProductRepositoryInterface $productRepository
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->mediaConfig = $mediaConfig;
        $this->scopeConfig = $scopeConfig;
        $this->stockRegistry = $stockRegistry;
        $this->configurableType = $configurableType;
        $this->groupedType = $groupedType;
        $this->productRepository = $productRepository;
    }

    public function buildProducts(int $storeId, ?int $limit = null, ?array $entityIds = null): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'sku', 'price', 'description', 'url_key', 'image', 'weight']);
        $collection->addStoreFilter($storeId);
        if ($limit !== null && $limit > 0) {
            $collection->setPageSize($limit);
        }
        if (!empty($entityIds)) {
            $collection->addFieldToFilter('entity_id', ['in' => $entityIds]);
        }
        $currency = (string)$this->scopeConfig->getValue('currency/options/base', ScopeInterface::SCOPE_STORES, $storeId);
        $store = $this->storeManager->getStore($storeId);
        $storeBaseUrl = rtrim((string)$store->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/');
        $products = [];

        foreach ($collection as $product) {
            $typeId = (string)$product->getTypeId();
            $typeId = (string)$product->getTypeId();
            $sku = (string)$product->getSku();
            $name = (string)$product->getName();
            $price = (float)$product->getFinalPrice();
            $weight = $product->getWeight();
            $weightVal = is_null($weight) ? null : (float)$weight;
            $description = (string)$product->getData('short_description');
            $urlKeyAttr = $product->getCustomAttribute('url_key');
            $urlKey = $urlKeyAttr ? (string)$urlKeyAttr->getValue() : null;
            $url = $this->buildFrontendProductUrl($product, $storeId, $storeBaseUrl, $urlKey);
            $image = (string)$product->getImage();
            $imageUrl = '';
            if ($image && $image !== 'no_selection') {
                $imageUrl = (string)$this->mediaConfig->getMediaUrl($image);
            } else {
                $parentIds = $this->configurableType->getParentIdsByChild((int)$product->getId());
                if (empty($parentIds)) {
                    $parentIds = $this->groupedType->getParentIdsByChild((int)$product->getId());
                }
                if (!empty($parentIds)) {
                    try {
                        $parent = $this->productRepository->getById((int)$parentIds[0], false, $storeId);
                        $parentImageAttr = $parent->getCustomAttribute('image');
                        $parentImage = $parentImageAttr ? (string)$parentImageAttr->getValue() : '';
                        if ($parentImage !== '' && $parentImage !== 'no_selection') {
                            $imageUrl = (string)$this->mediaConfig->getMediaUrl($parentImage);
                        }
                    } catch (\Throwable $e) {
                    }
                }
            }
            $createdIso = (new \DateTime((string)$product->getCreatedAt()))->format(\DateTime::ATOM);
            $updatedIso = (new \DateTime((string)$product->getUpdatedAt()))->format(\DateTime::ATOM);
            $categoryNames = [];
            try {
                $catCollection = $product->getCategoryCollection()->addAttributeToSelect('name');
                foreach ($catCollection as $cat) {
                    $categoryNames[] = (string)$cat->getName();
                }
            } catch (\Exception $e) {
            }
            $categoriesStr = $categoryNames ? implode(',', $categoryNames) : null;
            $tagsAttr = $product->getData('tags');
            $tagsStr = $tagsAttr ? (string)$tagsAttr : null;
            if ($product->getVisibility() === \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE) {
                $parentIds = $this->configurableType->getParentIdsByChild((int)$product->getId());
                if (empty($parentIds)) {
                    $parentIds = $this->groupedType->getParentIdsByChild((int)$product->getId());
                }
                if (!empty($parentIds)) {
                    try {
                        $parent = $this->productRepository->getById((int)$parentIds[0], false, $storeId);
                        $parentUrlKeyAttr = $parent->getCustomAttribute('url_key');
                        $parentUrlKey = $parentUrlKeyAttr ? (string)$parentUrlKeyAttr->getValue() : '';
                        $parentUrl = $this->buildFrontendProductUrl($parent, $storeId, $storeBaseUrl, $parentUrlKey);
                        $url = $parentUrl ?: $url;
                        $urlKey = $parentUrlKey ?: $urlKey;
                    } catch (\Throwable $e) {
                    }
                }
            }
            $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
            $salableQty = 0.0;
            $inStock = false;
            try {
                $stockItem = $this->stockRegistry->getStockItemBySku($sku, $websiteId);
                if ($stockItem) {
                    $salableQty = (float)$stockItem->getQty();
                    $inStock = (bool)$stockItem->getIsInStock();
                }
            } catch (\Throwable $e) {
                $salableQty = 0.0;
                $inStock = false;
            }
            if ($typeId === 'simple') {
                $payload = [
                    'storePrimaryId' => $sku,
                    'storeBaseProductId' => (string)$product->getId(),
                    'baseProductName' => $name,
                    'baseProductDescription' => $description ?: null,
                    'baseProductStoreCreatedDate' => $createdIso,
                    'baseProductStoreModifiedDate' => $updatedIso,
                    'categories' => $categoriesStr,
                    'tags' => $tagsStr,
                    'baseProductImages' => $imageUrl ? ['url' => $imageUrl] : null,
                    'baseProductUrl' => $url ?: null,
                    'baseProductUrlSlug' => $urlKey ?: null,
                    'baseProductWeight' => $weightVal,
                    'variantSku' => $sku,
                    'variantName' => $name,
                    'variantDescription' => $description ?: null,
                    'variantUrl' => $url ?: null,
                    'variantUrlSlug' => $urlKey ?: null,
                    'variantPriceCurrency' => $currency,
                    'variantPriceAmount' => $price,
                    'isVisible' => $product->getVisibility() !== \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE,
                    'stockStatus' => $inStock ? 'IN_STOCK' : 'OUT_OF_STOCK'

                ];
            } else {
                $payload = [
                    'storePrimaryId' => $sku,
                    'baseProductName' => $name,
                    'variantName' => $name,
                    'variantSku' => $sku,
                    'variantPriceAmount' => $price,
                    'variantPriceCurrency' => $currency
                ];
            }

            // Remove nulls to avoid sending invalid keys
            $products[] = array_filter(
                $payload,
                static function ($v) {
                    return $v !== null && $v !== '';
                }
            );
        }
        return $products;
    }

    private function buildFrontendProductUrl(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        int $storeId,
        string $storeBaseUrl,
        ?string $fallbackUrlKey
    ): string {
        $requestPath = null;
        try {
            $store = $this->storeManager->getStore($storeId);
            $rewrite = $product->getUrlModel()->getUrlRewrite();
            if ($rewrite && method_exists($rewrite, 'getRequestPath')) {
                $requestPath = (string)$rewrite->getRequestPath();
            }
        } catch (\Throwable $e) {
        }
        if (!$requestPath) {
            $urlSuffix = (string)$this->scopeConfig->getValue(
                'catalog/seo/product_url_suffix',
                ScopeInterface::SCOPE_STORES,
                $storeId
            ) ?: '';
            $requestPath = trim((string)$fallbackUrlKey ?? '', '/');
            if ($requestPath !== '' && $urlSuffix !== '' && strpos($requestPath, $urlSuffix) === false) {
                $requestPath .= $urlSuffix;
            }
        }
        $requestPath = ltrim($requestPath, '/');
        if ($requestPath === '') {
            return '';
        }
        return $storeBaseUrl . '/' . $requestPath;
    }
}
