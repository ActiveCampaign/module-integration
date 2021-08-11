<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace ActiveCampaign\AbandonedCart\Model\ResourceModel\Quote;

/**
 * Quotes collection
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Collection extends \Magento\Quote\Model\ResourceModel\Quote\Collection
{
	/**
     * @var string
     */
    protected $_idFieldName = 'entity_id';
}
