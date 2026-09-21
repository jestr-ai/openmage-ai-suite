<?php

class AiNative_Mcp_Model_Tool_Catalog_SearchProducts extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'search_products';
    }

    public function getDescription(): string
    {
        return 'Search the catalog by free text (matched against name, SKU and description), with optional filters for SKU prefix, category id, status, visibility, product type, price range and stock. Returns a page of compact product rows (id, sku, name, type, status, price, qty, in_stock). Use get_product for full details of one product.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'query' => ['type' => 'string', 'description' => 'Free text or SKU fragment. Optional if other filters are given.'],
            'category_id' => ['type' => 'integer'],
            'status' => ['type' => 'string', 'enum' => ['enabled', 'disabled', 'any'], 'description' => 'Default any.'],
            'type' => ['type' => 'string', 'description' => 'simple, configurable, grouped, bundle, virtual, downloadable'],
            'in_stock' => ['type' => 'boolean', 'description' => 'Only products flagged in stock.'],
            'min_price' => ['type' => 'number'],
            'max_price' => ['type' => 'number'],
            'store_id' => ['type' => 'integer', 'description' => 'Store view id for store-specific names/prices. Default: admin (0).'],
            'sort' => ['type' => 'string', 'enum' => ['relevance', 'name', 'price_asc', 'price_desc', 'newest', 'updated'], 'description' => 'Default relevance.'],
            'page' => ['type' => 'integer', 'minimum' => 1],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Default 20.'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/catalog/products';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $storeId = $this->int($args, 'store_id', 0, 0);
        $store = Mage::app()->getStore($storeId);
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'sku', 'status', 'visibility', 'price', 'special_price', 'type_id', 'updated_at', 'created_at', 'thumbnail'])
            ->joinField('qty', 'cataloginventory/stock_item', 'qty', 'product_id=entity_id', '{{table}}.stock_id=1', 'left')
            ->joinField('is_in_stock', 'cataloginventory/stock_item', 'is_in_stock', 'product_id=entity_id', '{{table}}.stock_id=1', 'left');

        $query = $this->str($args, 'query');
        if ($query !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';
            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => $like],
                ['attribute' => 'sku', 'like' => $like],
                ['attribute' => 'description', 'like' => $like],
            ]);
        }
        if (($cat = $this->int($args, 'category_id')) > 0) {
            $collection->addCategoryFilter(Mage::getModel('catalog/category')->load($cat));
        }
        $status = $this->str($args, 'status', 'any');
        if ($status === 'enabled') {
            $collection->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        } elseif ($status === 'disabled') {
            $collection->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
        }
        if (($type = $this->str($args, 'type')) !== '') {
            $collection->addAttributeToFilter('type_id', $type);
        }
        if ($this->bool($args, 'in_stock')) {
            $collection->addFieldToFilter('is_in_stock', 1);
        }
        if (isset($args['min_price'])) {
            $collection->addAttributeToFilter('price', ['gteq' => (float) $args['min_price']]);
        }
        if (isset($args['max_price'])) {
            $collection->addAttributeToFilter('price', ['lteq' => (float) $args['max_price']]);
        }
        switch ($this->str($args, 'sort', 'relevance')) {
            case 'name': $collection->addAttributeToSort('name', 'ASC'); break;
            case 'price_asc': $collection->addAttributeToSort('price', 'ASC'); break;
            case 'price_desc': $collection->addAttributeToSort('price', 'DESC'); break;
            case 'newest': $collection->addAttributeToSort('created_at', 'DESC'); break;
            case 'updated': $collection->addAttributeToSort('updated_at', 'DESC'); break;
            default:
                if ($query !== '') {
                    // exact sku first, then name matches
                    $collection->getSelect()->order(new Zend_Db_Expr($collection->getConnection()->quoteInto('(e.sku = ?) DESC', $query)));
                }
                $collection->addAttributeToSort('entity_id', 'DESC');
        }

        return $this->paginate($collection, $this->page($args), $this->pageSize($args), fn(Mage_Catalog_Model_Product $p) => [
            'id' => (int) $p->getId(),
            'sku' => $p->getSku(),
            'name' => $p->getName(),
            'type' => $p->getTypeId(),
            'status' => (int) $p->getStatus() === Mage_Catalog_Model_Product_Status::STATUS_ENABLED ? 'enabled' : 'disabled',
            'visibility' => (int) $p->getVisibility(),
            'price' => $p->getPrice() !== null ? round((float) $p->getPrice(), 2) : null,
            'special_price' => $p->getSpecialPrice() !== null && $p->getSpecialPrice() !== '' ? round((float) $p->getSpecialPrice(), 2) : null,
            'currency' => $store->getBaseCurrencyCode(),
            'qty' => $p->getData('qty') !== null ? (float) $p->getData('qty') : null,
            'in_stock' => (bool) $p->getData('is_in_stock'),
            'updated_at' => $p->getUpdatedAt(),
        ]);
    }
}
