<?php
/**
 * Widget block for displaying ActiveCampaign customer sync status in system config.
 *
 */

namespace ActiveCampaign\Customer\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class Widget
 *
 * Renders the customer sync status widget in the Magento admin configuration page.
 */
class Widget extends Fieldset
{
    /**
     * Prepare layout by adding the customer sync status block.
     *
     * @return \Magento\Framework\View\Element\AbstractBlock
     */
    protected function prepareLayout()
    {
        $this->addChild('customer_sync_status', CustomerSyncStatus::class);

        return parent::_prepareLayout();
    }

    /**
     * Render the customer sync status HTML output.
     *
     * @param AbstractElement $element The form element being rendered.
     * @return string Rendered HTML.
     */
    public function render(AbstractElement $element)
    {
        return $this->getChildHtml('customer_sync_status');
    }
}
