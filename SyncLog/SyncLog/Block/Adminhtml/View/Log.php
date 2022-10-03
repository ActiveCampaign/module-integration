<?php

namespace ActiveCampaign\SyncLog\Block\Adminhtml\View;

use ActiveCampaign\SyncLog\Model\SyncLog;
use Magento\Framework\App\RequestInterface;

class Log extends \Magento\Framework\View\Element\Template
{
    protected $syncLogModelFactory;
    protected $request;

    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        SyncLog $syncLogModelFactory,
        RequestInterface $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->syncLogModelFactory = $syncLogModelFactory;
        $this->_request = $request;
    }

    public function getLogCollection()
    {
        $param = $this->getRequest()->getParams();
        $collection = $this->syncLogModelFactory->getCollection()
            ->addFieldToFilter('id', $param['id'])->getData();
        //echo "<pre>";print_r($collection);exit;
        return $collection;
    }
}
