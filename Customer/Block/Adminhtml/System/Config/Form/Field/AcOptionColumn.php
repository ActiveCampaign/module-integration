<?php

declare(strict_types=1);
namespace ActiveCampaign\Customer\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\View\Element\Html\Select;

class AcOptionColumn extends Select
{
    private $curl;
    private $acHelper;
    const AC_LIMIT = 20;
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \ActiveCampaign\Core\Helper\Curl $curl,
        \ActiveCampaign\Core\Helper\Data $acHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->curl = $curl;
        $this->acHelper = $acHelper;
    }
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    public function setInputId($value)
    {
        return $this->setId($value);
    }
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    private function getSourceOptions(): array
    {
        $data = [];
        $fields=[];
        if ($this->acHelper->isEnabled() && $this->acHelper->getConnectionId()) {
            $count =0;
            $total = self::AC_LIMIT ;
            while ($count < $total) {
                $data = $this->curl->createConnection('GET', '/fields?limit='. self::AC_LIMIT .'&offset='.$count, [], []);
                if (count($data) && isset($data['data']) && isset($data['data']['fields']) && count($data['data']['fields'])) {

                    $data = $data['data'];
                    foreach ($data['fields'] as $opt) {
                        $fields[]=['label' => $opt['title'], 'value'=> $opt['id']];
                    }
                }
                $total = $data['meta']['total'];
                $count +=  self::AC_LIMIT;
            }
            
        }

        return  $fields;
    }

    protected function getCustomerAtt()
    {
        $ret = [];
        $collection = $this->attrCollection->create();

        foreach ($collection as $item) {
            $ret[] = ['label' => $item->getFrontendLabel(), 'value' => $item->getId() ];
        }


        return $ret;
    }
}
