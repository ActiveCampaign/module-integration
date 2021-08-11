<?php
declare(strict_types=1);
namespace ActiveCampaign\Core\Block\Adminhtml\System\Config;

use ActiveCampaign\Core\Helper\Data as ActiveCampaignHelper;
use Magento\Store\Model\ScopeInterface;

class ConnectActiveCampaign extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Set template to itself
     *
     * @return $this
     * @since 100.1.0
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setTemplate('ActiveCampaign_Core::system/config/connection.phtml');
        return $this;
    }

    /**
     * Unset some non-related element parameters
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     * @since 100.1.0
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element = clone $element;
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     * @since 100.1.0
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $currentButton = $this->escapeHtmlAttr(__('Connect'));
        $successButton = $this->escapeHtmlAttr(__('Disconnect'));
        $ajaxUrl = $this->_urlBuilder->getUrl('activecampaign/system_config/connect');
        $connection = true;
        $isConnected = $this->isConnected();
        if ($isConnected) {
            $currentButton = $this->escapeHtmlAttr(__('Disconnect'));
            $successButton = $this->escapeHtmlAttr(__('Connect'));
            $ajaxUrl = $this->_urlBuilder->getUrl('activecampaign/system_config/disconnect');
            $connection = false;
        }
        $this->addData(
            [
                'button_label' => __($currentButton),
                'html_id' => $element->getHtmlId(),
                'ajax_url' => $ajaxUrl,
                'connection' => $connection,
                'success_text' => $successButton,
                'field_mapping' => str_replace('"', '\\"', json_encode($this->_getFieldMapping()))
            ]
        );

        return $this->_toHtml();
    }

    /**
     * Returns configuration fields required to perform the ping request
     *
     * @return array
     * @since 100.1.0
     */
    protected function _getFieldMapping()
    {
        return [
            'status' => 'active_campaign_general_status',
            'api_url' => 'active_campaign_general_api_url',
            'api_key' => 'active_campaign_general_api_key',
            'store' => 'store_switcher'
        ];
    }

    /**
     * @return array
     */
    public function getConnectedStores()
    {
        $retuen = [];
        $stores = $this->_storeManager->getStores();
        foreach ($stores as $store) {
            $connectionId = $this->_scopeConfig->getValue(
                ActiveCampaignHelper::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            $retuen[$store->getId()]['name'] = $store->getName();
            $retuen[$store->getId()]['status'] = $connectionId;
        }

        return $retuen;
    }

    /**
     * @return bool
     */
    public function isDefaultConfig()
    {
        $store = $this->getRequest()->getParam('store');
        return ($store) ? false : true;
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

    /**
     * @return string
     */
    public function getCacheUrl()
    {
        return $this->_urlBuilder->getUrl('adminhtml/cache');
    }
}
