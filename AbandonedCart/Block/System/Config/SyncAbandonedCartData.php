<?php
/**
 * Copyright © Wagento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ActiveCampaign\AbandonedCart\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteResourceCollectionFactory;
use ActiveCampaign\AbandonedCart\Model\Config\CronConfig;

class SyncAbandonedCartData extends Field
{
    const AC_SYNC_STATUS = "ac_sync_status";
    /**
     * @var string
     */
    protected $_template = 'ActiveCampaign_AbandonedCart::system/config/sync_abandoned_cart_data.phtml';

    /**
     * @param Context $context
     * @param array $data
     * @param QuoteResourceCollectionFactory $quoteResourceCollectionFactory
     */
    public function __construct(
        Context $context,
        QuoteResourceCollectionFactory $quoteResourceCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->quoteResourceCollectionFactory = $quoteResourceCollectionFactory;
    }

    /**
     * Remove scope label
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for field sync button
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('acecommerce/index/syncaccart');
    }

    /**
     * Generate field sync button html
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            Button::class
        )->setData([
            'id' => 'ac_sync_abandoned_cart_button',
            'label' => __('Sync Abandoned Cart Data'),
        ]);
        return $button->toHtml();
    }

    /**
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAbandonedCartCollection()
    {
        $collection = $this->quoteResourceCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'main_table.is_active',
                '1'
            );
        return $collection;
    }

    /**
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSyncAbandonedCart()
    {
        $sync = $this->getAbandonedCartCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::SYNCED]
            ]
        );
        return count($sync);
    }

    /**
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTotalAbandonedCart()
    {
        $total = $this->getAbandonedCartCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::SYNCED],
                ['eq' => CronConfig::NOT_SYNCED],
                ['eq' => CronConfig::FAIL_SYNCED],
            ]
        );
        return count($total);
    }

    /**
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getNotSyncAbandonedCart()
    {
        $notSync = $this->getAbandonedCartCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::NOT_SYNCED],
            ]
        )->getData();
        return count($notSync);
    }

    /**
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFailedSync()
    {
        $failSync = $this->getAbandonedCartCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::FAIL_SYNCED],
            ]
        );
        return count($failSync);
    }
}
