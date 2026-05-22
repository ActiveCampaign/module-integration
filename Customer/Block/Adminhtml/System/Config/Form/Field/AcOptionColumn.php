<?php

declare(strict_types=1);
namespace ActiveCampaign\Customer\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\View\Element\Html\Select;

class AcOptionColumn extends Select
{
    private $curl;
    private $acHelper;
    const AC_LIMIT = 20;

    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \ActiveCampaign\Core\Helper\Curl $curl,
        \ActiveCampaign\Core\Helper\Data $acHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->curl = $curl;
        $this->acHelper = $acHelper;
    }
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    public function setInputId($value)
    {
        return $this->setId($value);
    }
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    private function getSourceOptions(): array
    {
        $fields = [];
        if ($this->acHelper->isEnabled() && $this->acHelper->getConnectionId()) {
            $count = 0;
            $total = self::AC_LIMIT;
            while ($count < $total) {
                $resp = $this->curl->createConnection('GET', 'fields?limit=' . self::AC_LIMIT . '&offset=' . $count, [], []);
                if (!empty($resp['success']) && !empty($resp['data']['fields']) && is_array($resp['data']['fields'])) {
                    foreach ($resp['data']['fields'] as $opt) {
                        if (isset($opt['title'], $opt['id'])) {
                            $fields[] = ['label' => $opt['title'], 'value' => $opt['id']];
                        }
                    }
                }
                if (!empty($resp['data']['meta']['total'])) {
                    $total = (int)$resp['data']['meta']['total'];
                } else {
                    break;
                }
                $count += self::AC_LIMIT;
            }
            
        }

        return  $fields;
    }
}
