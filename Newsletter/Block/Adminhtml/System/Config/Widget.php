<?php

namespace ActiveCampaign\Newsletter\Block\Adminhtml\System\Config;

class Widget extends \Magento\Config\Block\System\Config\Form\Fieldset
{

    /**
     * @return Widget
     */
    protected function _prepareLayout()
    {

        $this->addChild('newsletter_sync_status', NewsletterSyncStatus::class);

        return parent::_prepareLayout();
    }

    /**
     * @param  \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->getChildHtml('newsletter_sync_status');
    }
}
