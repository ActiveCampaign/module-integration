<?php

namespace ActiveCampaign\Customer\Block\Adminhtml\System\Config;

class Widget extends \Magento\Config\Block\System\Config\Form\Fieldset
{

    /**
     * {@inheritdoc}
     */
    protected function _prepareLayout()
    {
        $this->addChild('customer_sync_status', CustomerSyncStatus::class);

        return parent::_prepareLayout();
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->getChildHtml('customer_sync_status');
    }
}
