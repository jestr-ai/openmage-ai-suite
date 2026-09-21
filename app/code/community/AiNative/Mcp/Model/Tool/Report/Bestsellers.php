<?php

class AiNative_Mcp_Model_Tool_Report_Bestsellers extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'bestsellers';
    }

    public function getDescription(): string
    {
        return 'Top-selling products in a date range by quantity ordered (and revenue), computed from order items (excludes canceled orders). Returns sku, name, product_id, qty_ordered, revenue, orders.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD (default 30 days ago)'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD (default today)'],
            'store_id' => ['type' => 'integer'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Default 20'],
            'sort' => ['type' => 'string', 'enum' => ['qty', 'revenue'], 'description' => 'Default qty'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/report/products';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $tz = $this->storeTimezone(isset($args['store_id']) ? $this->int($args, 'store_id') : null);
        $from = $this->str($args, 'from') ?: (new DateTime('-30 days', new DateTimeZone($tz)))->format('Y-m-d');
        $to = $this->str($args, 'to') ?: (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
        $fromUtc = (new DateTime($from . ' 00:00:00', new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUtc = (new DateTime($to . ' 23:59:59', new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $resource = Mage::getSingleton('core/resource');
        $conn = $resource->getConnection('core_read');
        $select = $conn->select()
            ->from(['i' => $resource->getTableName('sales/order_item')], [
                'product_id' => 'i.product_id',
                'sku' => 'i.sku',
                'name' => 'i.name',
                'qty_ordered' => 'SUM(i.qty_ordered)',
                'revenue' => 'SUM(i.base_row_total - COALESCE(i.base_discount_amount,0))',
                'orders' => 'COUNT(DISTINCT i.order_id)',
            ])
            ->join(['o' => $resource->getTableName('sales/order')], 'o.entity_id = i.order_id', [])
            ->where('o.created_at >= ?', $fromUtc)->where('o.created_at <= ?', $toUtc)
            ->where('o.state <> ?', Mage_Sales_Model_Order::STATE_CANCELED)
            ->where('i.parent_item_id IS NULL')
            ->group(['i.product_id', 'i.sku'])
            ->order(($this->str($args, 'sort', 'qty') === 'revenue' ? 'revenue' : 'qty_ordered') . ' DESC')
            ->limit($this->int($args, 'limit', 20, 1, 100));
        if (isset($args['store_id'])) {
            $select->where('o.store_id = ?', $this->int($args, 'store_id'));
        }
        $rows = array_map(fn($r) => [
            'product_id' => (int) $r['product_id'],
            'sku' => $r['sku'],
            'name' => $r['name'],
            'qty_ordered' => (float) $r['qty_ordered'],
            'revenue' => round((float) $r['revenue'], 2),
            'orders' => (int) $r['orders'],
        ], $conn->fetchAll($select));
        return ['from' => $from, 'to' => $to, 'currency' => Mage::app()->getStore(0)->getBaseCurrencyCode(), 'items' => $rows];
    }
}
