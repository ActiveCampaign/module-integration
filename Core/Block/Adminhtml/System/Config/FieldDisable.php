<?php
namespace ActiveCampaign\Core\Block\Adminhtml\System\Config;

use ActiveCampaign\Core\Helper\Data as ActiveCampaignHelper;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

class FieldDisable extends Field
{
    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        if($this->isConnected()) {
            $element->setDisabled('disabled');
        }
        return $element->getElementHtml();
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        $store = $this->getRequest()->getParam('store');
        if ($store) {
            $connectionId = $this->_scopeConfig->getValue(
                ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                ScopeInterface::SCOPE_STORES,
                $store
            );

            return ($connectionId) ? true : false;
        } else {
            $stores = $this->_storeManager->getStores();
            foreach ($stores as $store) {
                $connectionId = $this->_scopeConfig->getValue(
                    ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
                if (!$connectionId) {
                    return false;
                }
            }
            return true;
        }
    }
}
