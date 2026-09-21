<?php

class AiNative_Assistant_Model_Tool_SearchCatalog extends AiNative_Assistant_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'search_catalog';
    }

    public function getDescription(): string
    {
        return 'Search products the shopper can buy. Use natural keywords (e.g. "waterproof jacket"), optionally a category id, price range, sale-only flag and sort. Returns product cards with the shopper\'s prices, availability and links. Always search before recommending; never invent products or prices. Try broader or alternative keywords if there are no results.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'query' => ['type' => 'string', 'description' => 'Keywords; split compound needs into separate searches.'],
            'category_id' => ['type' => 'integer'],
            'min_price' => ['type' => 'number'],
            'max_price' => ['type' => 'number'],
            'on_sale' => ['type' => 'boolean'],
            'sort' => ['type' => 'string', 'enum' => ['relevance', 'price_asc', 'price_desc', 'newest', 'bestselling']],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12, 'description' => 'Default 6.'],
        ]);
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $store = $this->store($context);
        $collection = $this->visibleCollection($store, $context);
        $query = $this->str($args, 'query');
        if ($query !== '') {
            $ids = $this->fulltextIds($query, $store);
            if ($ids === []) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';
                $collection->addAttributeToFilter([['attribute' => 'name', 'like' => $like], ['attribute' => 'sku', 'like' => $like]]);
            } else {
                $collection->addIdFilter($ids);
                if (($args['sort'] ?? 'relevance') === 'relevance') {
                    $collection->getSelect()->order(new Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', array_map('intval', $ids)) . ')'));
                }
            }
        }
        if (($cat = $this->int($args, 'category_id')) > 0) {
            $collection->addCategoryFilter(Mage::getModel('catalog/category')->load($cat));
        }
        if (isset($args['min_price'])) {
            $collection->getSelect()->where('price_index.min_price >= ?', (float) $args['min_price']);
        }
        if (isset($args['max_price'])) {
            $collection->getSelect()->where('price_index.min_price <= ?', (float) $args['max_price']);
        }
        if ($this->bool($args, 'on_sale')) {
            $collection->getSelect()->where('price_index.final_price < price_index.price');
        }
        switch ($this->str($args, 'sort', 'relevance')) {
            case 'price_asc': $collection->getSelect()->order('price_index.min_price ASC'); break;
            case 'price_desc': $collection->getSelect()->order('price_index.min_price DESC'); break;
            case 'newest': $collection->addAttributeToSort('created_at', 'DESC'); break;
            case 'bestselling':
                $collection->getSelect()->joinLeft(
                    ['sold' => new Zend_Db_Expr('(SELECT product_id, SUM(qty_ordered) q FROM ' . $collection->getTable('sales/order_item') . ' GROUP BY product_id)')],
                    'sold.product_id = e.entity_id',
                    [],
                )->order('sold.q DESC');
                break;
        }
        $limit = $this->int($args, 'limit', 6, 1, 12);
        $collection->setPageSize($limit)->setCurPage(1);
        $items = [];
        foreach ($collection as $p) {
            $items[] = $this->productCard($p, $store);
        }
        $this->rememberProducts($context, $items);
        return ['query' => $query, 'count' => count($items), 'products' => $items, 'hint' => $items ? null : 'No results. Try different or broader keywords, or list_categories to browse.'];
    }

    /** @return int[] ordered by relevance */
    private function fulltextIds(string $query, Mage_Core_Model_Store $store): array
    {
        try {
            $q = Mage::getModel('catalogsearch/query')->setStoreId((int) $store->getId())->setQueryText($query);
            if (mb_strlen($query) < (int) Mage::getStoreConfig('catalog/search/min_query_length', $store)) {
                return [];
            }
            $q->setQueryText(mb_substr($query, 0, 128));
            Mage::getResourceModel('catalogsearch/fulltext')->prepareResult(Mage::getModel('catalogsearch/fulltext'), $query, $q);
            $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
            $table = Mage::getSingleton('core/resource')->getTableName('catalogsearch/result');
            $select = $conn->select()->from($table, ['product_id'])->where('query_id = ?', (int) $q->getId())->order('relevance DESC')->limit(60);
            return array_map('intval', $conn->fetchCol($select));
        } catch (Throwable $e) {
            Mage::helper('ainative_core')->debug('fulltext search failed: ' . $e->getMessage());
            return [];
        }
    }
}
