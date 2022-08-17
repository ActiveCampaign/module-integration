<?php
declare(strict_types=1);

namespace ActiveCampaign\Core\Block\Adminhtml\System\Config;

class FieldDisable extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * @inheritdoc
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        if ($this->isConnected()) {
            $element->setDisabled('disabled');
        }

        return $element->getElementHtml();
    }

    /**
     * Is connected
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        if ($store = $this->getRequest()->getParam('store')) {
            $connectionId = $this->_scopeConfig->getValue(
                \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $store
            );

            return (bool)$connectionId;
        }

        $stores = $this->_storeManager->getStores();

        foreach ($stores as $store) {
            $connectionId = $this->_scopeConfig->getValue(
                \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            if (!$connectionId) {
                return false;
            }
        }

        return true;
    }
}
