<?php

class AiNative_Assistant_Model_Tool_ListCategories extends AiNative_Assistant_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'list_categories';
    }

    public function getDescription(): string
    {
        return 'Browse the store\'s category tree (active, in-menu categories with ids, names, product counts and links). Use the ids with search_catalog, or to suggest where to browse.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'parent_id' => ['type' => 'integer', 'description' => 'Children of this category; default = store root.'],
            'depth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 3, 'description' => 'Default 2.'],
        ]);
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $store = $this->store($context);
        $rootId = $this->int($args, 'parent_id') ?: (int) $store->getRootCategoryId();
        $root = Mage::getModel('catalog/category')->setStoreId((int) $store->getId())->load($rootId);
        if (!$root->getId()) {
            $this->fail('Category not found.');
        }
        $maxLevel = (int) $root->getLevel() + $this->int($args, 'depth', 2, 1, 3);
        $collection = Mage::getModel('catalog/category')->getCollection()->setStoreId((int) $store->getId())
            ->addAttributeToSelect(['name', 'url_key', 'url_path'])
            ->addIsActiveFilter()->addAttributeToFilter('include_in_menu', 1)
            ->addFieldToFilter('path', ['like' => $root->getPath() . '/%'])
            ->addFieldToFilter('level', ['lteq' => $maxLevel])
            ->setProductStoreId((int) $store->getId())->setLoadProductCount(true)->addOrderField('path');
        $items = [];
        foreach ($collection as $c) {
            $items[] = ['id' => (int) $c->getId(), 'name' => $c->getName(), 'parent_id' => (int) $c->getParentId(), 'level' => (int) $c->getLevel(), 'product_count' => (int) $c->getProductCount(), 'url' => $c->getUrl()];
        }
        return ['categories' => $items];
    }
}
