<?php

class AiNative_Mcp_Model_Tool_Catalog_ListCategories extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'list_categories';
    }

    public function getDescription(): string
    {
        return 'List the category tree (id, name, parent, level, path, is_active, product_count, url_key) for a store\'s root category, optionally only children of a given parent. Use category ids with search_products.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'store_id' => ['type' => 'integer', 'description' => 'Store view; determines the root category and names. Default: default store view.'],
            'parent_id' => ['type' => 'integer', 'description' => 'Only descendants of this category.'],
            'max_depth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 6, 'description' => 'Levels below the root/parent to include. Default 3.'],
            'include_inactive' => ['type' => 'boolean'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/catalog/categories';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $storeId = isset($args['store_id']) ? $this->int($args, 'store_id', 0, 0) : (int) Mage::app()->getDefaultStoreView()->getId();
        $store = Mage::app()->getStore($storeId);
        $rootId = $this->int($args, 'parent_id') ?: (int) $store->getRootCategoryId();
        $root = Mage::getModel('catalog/category')->setStoreId($storeId)->load($rootId);
        if (!$root->getId()) {
            $this->fail('Category not found.');
        }
        $maxLevel = (int) $root->getLevel() + $this->int($args, 'max_depth', 3, 1, 6);
        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'is_active', 'url_key', 'include_in_menu'])
            ->addFieldToFilter('path', ['like' => $root->getPath() . '/%'])
            ->addFieldToFilter('level', ['lteq' => $maxLevel])
            ->setProductStoreId($storeId)
            ->setLoadProductCount(true)
            ->addOrderField('path');
        if (!$this->bool($args, 'include_inactive')) {
            $collection->addAttributeToFilter('is_active', 1);
        }
        $items = [];
        foreach ($collection as $cat) {
            $items[] = [
                'id' => (int) $cat->getId(),
                'name' => $cat->getName(),
                'parent_id' => (int) $cat->getParentId(),
                'level' => (int) $cat->getLevel(),
                'path' => $cat->getPath(),
                'is_active' => (bool) $cat->getIsActive(),
                'include_in_menu' => (bool) $cat->getIncludeInMenu(),
                'url_key' => $cat->getUrlKey(),
                'product_count' => (int) $cat->getProductCount(),
            ];
        }
        return ['store_id' => $storeId, 'root' => ['id' => (int) $root->getId(), 'name' => $root->getName(), 'level' => (int) $root->getLevel()], 'count' => count($items), 'categories' => $items];
    }
}
