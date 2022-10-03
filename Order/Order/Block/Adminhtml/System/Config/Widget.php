<?php

namespace ActiveCampaign\Order\Block\Adminhtml\System\Config;

class Widget extends \Magento\Config\Block\System\Config\Form\Fieldset
{

    /**
     * @return Widget
     */
    protected function _prepareLayout()
    {
        $this->addChild('order_sync_status', OrderSyncStatus::class);

        return parent::_prepareLayout();
    }

    /**
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->getChildHtml('order_sync_status');
    }
}
