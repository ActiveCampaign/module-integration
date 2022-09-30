<?php
declare(strict_types=1);

namespace ActiveCampaign\Core\Block\Adminhtml\System\Config;

class ConnectActiveCampaign extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Set template to itself
     *
     * @return ConnectActiveCampaign
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
     *
     * @return string
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
     *
     * @return string
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
                'button_label'  => __($currentButton),
                'html_id'       => $element->getHtmlId(),
                'ajax_url'      => $ajaxUrl,
                'connection'    => $connection,
                'success_text'  => $successButton,
                'field_mapping' => str_replace('"', '\\"', json_encode($this->_getFieldMapping()))
            ]
        );

        return $this->_toHtml();
    }

    /**
     * Returns configuration fields required to perform the ping request
     *
     * @return array
     */
    protected function _getFieldMapping()
    {
        return [
            'status'    => 'active_campaign_general_status',
            'api_url'   => 'active_campaign_general_api_url',
            'api_key'   => 'active_campaign_general_api_key',
            'store'     => 'store_switcher'
        ];
    }

    /**
     * Get connected stores
     *
     * @return array
     */
    public function getConnectedStores(): array
    {
        $result = [];

        foreach ($this->_storeManager->getStores() as $store) {
            $connectionId = $this->_scopeConfig->getValue(
                \ActiveCampaign\Core\Helper\Data::ACTIVE_CAMPAIGN_GENERAL_CONNECTION_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $store->getId()
            );

            $result[$store->getId()]['name'] = $store->getName();
            $result[$store->getId()]['status'] = $connectionId;
        }

        return $result;
    }

    /**
     * Is default config
     *
     * @return bool
     */
    public function isDefaultConfig(): bool
    {
        return !$this->getRequest()->getParam('store');
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

    /**
     * Get cache URL
     *
     * @return string
     */
    public function getCacheUrl(): string
    {
        return $this->_urlBuilder->getUrl('adminhtml/cache');
    }
}
