<?php

class AiNative_Mcp_Model_Tool_Catalog_GetStock extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'get_stock';
    }

    public function getDescription(): string
    {
        return 'Get inventory for one or more SKUs / product ids: qty, is_in_stock, manage_stock, min qty, backorders, low-stock notify threshold. Up to 50 items per call.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'skus' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 50],
            'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 50],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/catalog/products';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $ids = array_map('intval', (array) ($args['ids'] ?? []));
        foreach ((array) ($args['skus'] ?? []) as $sku) {
            $id = (int) Mage::getModel('catalog/product')->getIdBySku((string) $sku);
            if ($id) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            $this->fail('No matching products. Provide skus or ids.');
        }
        $products = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect(['name', 'sku'])->addIdFilter($ids);
        $items = [];
        foreach ($products as $p) {
            $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($p);
            $items[] = [
                'id' => (int) $p->getId(),
                'sku' => $p->getSku(),
                'name' => $p->getName(),
                'qty' => (float) $stock->getQty(),
                'is_in_stock' => (bool) $stock->getIsInStock(),
                'manage_stock' => (bool) $stock->getManageStock(),
                'min_qty' => (float) $stock->getMinQty(),
                'notify_stock_qty' => (float) $stock->getNotifyStockQty(),
                'backorders' => (int) $stock->getBackorders(),
                'qty_increments' => (float) $stock->getQtyIncrements(),
            ];
        }
        return ['items' => $items];
    }
}
