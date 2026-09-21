<?php

class AiNative_Mcp_Model_Tool_Report_LowStock extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'low_stock';
    }

    public function getDescription(): string
    {
        return 'Products with managed stock whose qty is at or below a threshold (default: each item\'s own notify-for-quantity-below setting, falling back to the global one). Only simple/virtual/downloadable items with manage_stock enabled. Sorted by qty ascending.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'threshold' => ['type' => 'number', 'description' => 'Override threshold for all items.'],
            'include_disabled' => ['type' => 'boolean'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'description' => 'Default 50'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/report/products/lowstock';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect(['name', 'sku', 'status', 'type_id'])
            ->addAttributeToFilter('type_id', ['in' => ['simple', 'virtual', 'downloadable']])
            ->joinField('qty', 'cataloginventory/stock_item', 'qty', 'product_id=entity_id', '{{table}}.stock_id=1', 'inner')
            ->joinField('manage_stock', 'cataloginventory/stock_item', 'manage_stock', 'product_id=entity_id', null, 'inner')
            ->joinField('use_config_manage_stock', 'cataloginventory/stock_item', 'use_config_manage_stock', 'product_id=entity_id', null, 'inner')
            ->joinField('notify_stock_qty', 'cataloginventory/stock_item', 'notify_stock_qty', 'product_id=entity_id', null, 'inner')
            ->joinField('use_config_notify_stock_qty', 'cataloginventory/stock_item', 'use_config_notify_stock_qty', 'product_id=entity_id', null, 'inner')
            ->joinField('is_in_stock', 'cataloginventory/stock_item', 'is_in_stock', 'product_id=entity_id', null, 'inner');
        $globalManage = (int) Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);
        $globalNotify = (float) Mage::getStoreConfig(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_NOTIFY_STOCK_QTY);
        $select = $collection->getSelect();
        $select->where("(at_use_config_manage_stock.use_config_manage_stock = 1 AND {$globalManage} = 1) OR (at_use_config_manage_stock.use_config_manage_stock = 0 AND at_manage_stock.manage_stock = 1)");
        if (isset($args['threshold'])) {
            $select->where('at_qty.qty <= ?', (float) $args['threshold']);
        } else {
            $select->where("at_qty.qty <= IF(at_use_config_notify_stock_qty.use_config_notify_stock_qty = 1, {$globalNotify}, at_notify_stock_qty.notify_stock_qty)");
        }
        if (!$this->bool($args, 'include_disabled')) {
            $collection->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        }
        $collection->getSelect()->order('at_qty.qty ASC');
        $collection->setPageSize($this->int($args, 'limit', 50, 1, 200))->setCurPage(1);
        $items = [];
        foreach ($collection as $p) {
            $items[] = [
                'id' => (int) $p->getId(),
                'sku' => $p->getSku(),
                'name' => $p->getName(),
                'qty' => (float) $p->getData('qty'),
                'is_in_stock' => (bool) $p->getData('is_in_stock'),
                'notify_below' => (int) $p->getData('use_config_notify_stock_qty') ? $globalNotify : (float) $p->getData('notify_stock_qty'),
                'status' => (int) $p->getStatus() === 1 ? 'enabled' : 'disabled',
            ];
        }
        return ['count' => count($items), 'global_notify_threshold' => $globalNotify, 'items' => $items];
    }
}
