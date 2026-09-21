<?php

class AiNative_Mcp_Model_Tool_Catalog_ListAttributes extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'list_attributes';
    }

    public function getDescription(): string
    {
        return 'List product attributes (code, label, input type, options for selects, whether searchable/filterable/used in listing) and the attribute sets they belong to. Use to learn valid attribute codes and option values before update_product or filtering.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'attribute_set_id' => ['type' => 'integer', 'description' => 'Restrict to one attribute set.'],
            'code' => ['type' => 'string', 'description' => 'Exact attribute code to describe (returns its options).'],
            'include_system' => ['type' => 'boolean', 'description' => 'Include non-visible system attributes. Default false.'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/catalog/attributes';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $entityTypeId = Mage::getModel('eav/entity')->setType(Mage_Catalog_Model_Product::ENTITY)->getTypeId();
        $sets = [];
        foreach (Mage::getModel('eav/entity_attribute_set')->getCollection()->setEntityTypeFilter($entityTypeId) as $set) {
            $sets[] = ['id' => (int) $set->getId(), 'name' => $set->getAttributeSetName()];
        }
        /** @var Mage_Catalog_Model_Resource_Product_Attribute_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_attribute_collection');
        if (($setId = $this->int($args, 'attribute_set_id')) > 0) {
            $collection->setAttributeSetFilter($setId);
        }
        if (($code = $this->str($args, 'code')) !== '') {
            $collection->addFieldToFilter('attribute_code', $code);
        } elseif (!$this->bool($args, 'include_system')) {
            $collection->addVisibleFilter();
        }
        $collection->setOrder('frontend_label', 'ASC');
        $items = [];
        foreach ($collection as $attr) {
            $row = [
                'code' => $attr->getAttributeCode(),
                'label' => $attr->getFrontendLabel(),
                'input' => $attr->getFrontendInput(),
                'backend_type' => $attr->getBackendType(),
                'required' => (bool) $attr->getIsRequired(),
                'scope' => $attr->getIsGlobal() == 1 ? 'global' : ($attr->getIsGlobal() == 2 ? 'website' : 'store'),
                'searchable' => (bool) $attr->getIsSearchable(),
                'filterable' => (bool) $attr->getIsFilterable(),
                'used_in_listing' => (bool) $attr->getUsedInProductListing(),
                'user_defined' => (bool) $attr->getIsUserDefined(),
            ];
            if ($attr->usesSource() && in_array($attr->getFrontendInput(), ['select', 'multiselect'], true) && ($code !== '' || count($items) < 60)) {
                try {
                    $opts = [];
                    foreach ($attr->getSource()->getAllOptions(false) as $o) {
                        if ($o['value'] !== '' && $o['value'] !== null) {
                            $opts[] = ['value' => is_numeric($o['value']) ? (int) $o['value'] : $o['value'], 'label' => (string) $o['label']];
                        }
                    }
                    $row['options'] = array_slice($opts, 0, 200);
                } catch (Throwable) {
                }
            }
            $items[] = $row;
        }
        return ['attribute_sets' => $sets, 'count' => count($items), 'attributes' => $items];
    }
}
