<?php
/**
 * Copyright © Wagento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ActiveCampaign\AbandonedCart\Ui\Component\Listing\Column;
 
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
     
    const URL_PATH_SYNC = 'acecommerce/index/sync';
    
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;
 
    /**
     * @param ContextInterface   $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface       $urlBuilder
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }
    
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['entity_id'])) {
                    $item[$this->getData('name')] = [
                        'sync' => [
                            'href' => $this->urlBuilder->getUrl(
                                static::URL_PATH_SYNC,
                                [
                                    'entity_id' => $item['entity_id']
                                ]
                            ),
                            'label' => __('Sync')
                        ]
                    ];
                }
            }
        }
 
        return $dataSource;
    }
}
