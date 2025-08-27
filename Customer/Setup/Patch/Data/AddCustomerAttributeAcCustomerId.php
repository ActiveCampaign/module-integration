<?php

namespace ActiveCampaign\Customer\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Set;
use Magento\Eav\Model\Entity\Attribute\SetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class AddCustomerAttributeAcCustomerId implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;
    /**
     * @var CustomerSetup
     */
    protected $customerSetupFactory;

    /**
     * @var SetFactory
     */
    protected $attributeSetFactory;

    /**
     * Constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory     $customerSetupFactory
     * @param SetFactory               $attributeSetFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory,
        SetFactory $attributeSetFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /**
 * @var CustomerSetup $customerSetup
*/
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        /**
 * @var $attributeSet Set
*/
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);
        if (!$customerSetup->getAttributeId(Customer::ENTITY, 'ac_customer_id')) {
            $customerSetup->addAttribute(
                Customer::ENTITY,
                'ac_customer_id',
                [
                    'type' => 'int',
                    'input' => 'hidden',
                    'label' => 'ActiveCampaign Customer Id',
                    'default' => 0,
                    'required' => false,
                    'visible' => false,
                    'system' => false,
                    'user_defined' => false,
                    'is_visible_in_grid' => true,
                    'is_used_in_grid' => true,
                    'is_filterable_in_grid' => true,
                    'is_searchable_in_grid' => true,
                    'position' => 999
                ]
            );

            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'ac_customer_id');
            $attribute->addData(
                [
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId
                ]
            );
            $attribute->save();
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /**
 * @var CustomerSetup $customerSetup
*/
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerSetup->removeAttribute(\Magento\Customer\Model\Customer::ENTITY, 'ac_customer_id');

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [

        ];
    }
}
