<?php

declare(strict_types=1);
namespace ActiveCampaign\Customer\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\View\Element\Html\Select;

class CustomerOptionColumn extends Select
{
    /**
     * @var \Magento\Customer\Model\ResourceModel\Attribute\CollectionFactory
     */
    private $attrCollection;

    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Customer\Model\ResourceModel\Attribute\CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context,$data);
        $this->attrCollection = $collectionFactory;
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
        $addressFields = [
            ['label' => 'Default Shipping ZIP', 'value' => 'shipping__postcode'],
            ['label' => 'Default Shipping City', 'value' => 'shipping__city'],
            ['label' => 'Default Shipping Telephone', 'value' => 'shipping__telephone'],
            ['label' => 'Default Shipping Region', 'value' => 'shipping__region'],
            ['label' => 'Default Shipping Company', 'value' => 'shipping__company'],
            ['label' => 'Default Billing ZIP', 'value' => 'billing__postcode'],
            ['label' => 'Default Billing City', 'value' => 'billing__city'],
            ['label' => 'Default Billing Telephone', 'value' => 'billing__telephone'],
            ['label' => 'Default Billing Region', 'value' => 'billing__region'],
            ['label' => 'Default Billing Company', 'value' => 'billing__company'],
        ];
        return  array_merge($addressFields, $this->getCustomerAtt());

    }

    protected function getCustomerAtt()
    {
        $ret = [];
        $collection = $this->attrCollection->create();

        foreach ($collection as $item) {
            $ret[] = ['label' => $item->getFrontendLabel(), 'value' => $item->getAttributeCode() ];
        }


        return $ret;
    }
}
