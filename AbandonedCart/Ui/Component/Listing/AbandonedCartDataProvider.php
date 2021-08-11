<?php
/**
 * Copyright © Wagento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace ActiveCampaign\AbandonedCart\Ui\Component\Listing;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class AbandonedCartDataProvider extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
		$this->getSelect()
			->join(
				['quote_address'],
				'main_table.entity_id = quote_address.quote_id',
				['*']
			)
			->where("main_table.is_active = 1 AND quote_address.address_type = 'billing'");
        return $this;
    }
}
