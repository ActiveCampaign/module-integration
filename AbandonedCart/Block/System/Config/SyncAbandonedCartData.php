<?php
/**
 * Copyright © Wagento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace ActiveCampaign\AbandonedCart\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteResourceCollectionFactory;
use ActiveCampaign\AbandonedCart\Model\Config\CronConfig;

class SyncAbandonedCartData extends Field
{
    public const AC_SYNC_STATUS = 'ac_sync_status';

    /**
     * @var string
     */
    protected $_template = 'ActiveCampaign_AbandonedCart::system/config/sync_abandoned_cart_data.phtml';

    /**
     * @var QuoteResourceCollectionFactory
     */
    protected $quoteResourceCollectionFactory;

    /**
     * Construct
     *
     * @param Context $context
     * @param QuoteResourceCollectionFactory $quoteResourceCollectionFactory
     * @param array $data
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
     *
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
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for field sync button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('acecommerce/index/syncaccart');
    }

    /**
     * Generate field sync button html
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            Button::class
        )->setData([
            'id'    => 'ac_sync_abandoned_cart_button',
            'label' => __('Sync Abandoned Cart Data'),
        ]);

        return $button->toHtml();
    }

    /**
     * Get abandoned cart collection
     *
     * @return \Magento\Quote\Model\ResourceModel\Quote\Collection
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAbandonedCartCollection()
    {
        return $this->quoteResourceCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'main_table.is_active',
                '1'
            );
    }

    /**
     * Get sync abandoned cart
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSyncAbandonedCart()
    {
        $collection = $this->getAbandonedCartCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::SYNCED]
            ]
        );

        return $collection->getSize();
    }

    /**
     * Get total abandoned cart
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTotalAbandonedCart()
    {
        $collection = $this->getAbandonedCartCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::SYNCED],
                ['eq' => CronConfig::NOT_SYNCED],
                ['eq' => CronConfig::FAIL_SYNCED]
            ]
        )
        ->addFieldToFilter('items_count',['gt' => 0]);

        return $collection->getSize();
    }

    /**
     * Get not sync abandoned cart
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getNotSyncAbandonedCart()
    {
        $collection = $this->getAbandonedCartCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::NOT_SYNCED]
            ]
        )
            ->addFieldToFilter('items_count',['gt' => 0]);

        return $collection->getSize();
    }

    /**
     * Get failed sync
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFailedSync()
    {
        $collection = $this->getAbandonedCartCollection()->addFieldToFilter(
            self::AC_SYNC_STATUS,
            [
                ['eq' => CronConfig::FAIL_SYNCED]
            ]
        )
            ->addFieldToFilter('items_count',['gt' => 0]);

        return $collection->getSize();
    }
}
