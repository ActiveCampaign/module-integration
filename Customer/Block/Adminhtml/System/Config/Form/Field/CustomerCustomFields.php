<?php

namespace ActiveCampaign\Customer\Block\Adminhtml\System\Config\Form\Field;

use ActiveCampaign\Customer\Block\Adminhtml\System\Config\Form\Field\CustomerOptionColumn;

class CustomerCustomFields extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    private $customerOptions;
    private $acOptions;

    protected function _prepareArrayRow(\Magento\Framework\DataObject $row): void
    {
        $options = [];

        $row->setData('option_extra_attrs', $options);
    }


    protected function _prepareToRender()
    {
        $this->addColumn(
            'ac_customer_field_id',
            ['label' => __('Active Campaign'),
             'renderer' => $this->getAcField()]
        );
        $this->addColumn(
            'customer_field_id',
            [   'label' => __('Magento'),
                'renderer' => $this->getCustomerField()
            ]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    private function getCustomerField()
    {
        if (!$this->customerOptions) {
            $this->customerOptions = $this->getLayout()->createBlock(
                CustomerOptionColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->customerOptions;
    }

    private function getAcField()
    {
        if (!$this->acOptions) {
            $this->acOptions = $this->getLayout()->createBlock(
                AcOptionColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->acOptions;
    }
}
