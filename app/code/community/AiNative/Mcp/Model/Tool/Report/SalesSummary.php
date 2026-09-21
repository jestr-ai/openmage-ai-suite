<?php

class AiNative_Mcp_Model_Tool_Report_SalesSummary extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'sales_summary';
    }

    public function getDescription(): string
    {
        return 'Revenue report for a date range (store timezone): orders, items, revenue (grand total), discounts, shipping, tax, refunds, average order value, grouped by day, week or month, plus a totals row and a breakdown by status. Excludes canceled orders unless include_canceled. Amounts in base currency.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD (default: 30 days ago)'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD (default: today)'],
            'group_by' => ['type' => 'string', 'enum' => ['day', 'week', 'month', 'none'], 'description' => 'Default day.'],
            'store_id' => ['type' => 'integer'],
            'include_canceled' => ['type' => 'boolean'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/report/salesroot/sales';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $tz = $this->storeTimezone(isset($args['store_id']) ? $this->int($args, 'store_id') : null);
        $from = $this->str($args, 'from') ?: (new DateTime('-30 days', new DateTimeZone($tz)))->format('Y-m-d');
        $to = $this->str($args, 'to') ?: (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
        $fromUtc = $this->utc($from . ' 00:00:00', $tz);
        $toUtc = $this->utc($to . ' 23:59:59', $tz);

        $resource = Mage::getSingleton('core/resource');
        $conn = $resource->getConnection('core_read');
        $table = $resource->getTableName('sales/order');
        $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P'); // e.g. +02:00
        $localCreated = new Zend_Db_Expr("CONVERT_TZ(created_at, '+00:00', '{$offset}')");
        $groupBy = $this->str($args, 'group_by', 'day');
        $period = match ($groupBy) {
            'week' => new Zend_Db_Expr("DATE_FORMAT({$localCreated}, '%x-W%v')"),
            'month' => new Zend_Db_Expr("DATE_FORMAT({$localCreated}, '%Y-%m')"),
            'none' => new Zend_Db_Expr("'total'"),
            default => new Zend_Db_Expr("DATE({$localCreated})"),
        };
        $cols = [
            'period' => $period,
            'orders' => 'COUNT(*)',
            'items' => 'COALESCE(SUM(total_qty_ordered),0)',
            'revenue' => 'COALESCE(SUM(base_grand_total),0)',
            'subtotal' => 'COALESCE(SUM(base_subtotal),0)',
            'discount' => 'COALESCE(SUM(ABS(base_discount_amount)),0)',
            'shipping' => 'COALESCE(SUM(base_shipping_amount),0)',
            'tax' => 'COALESCE(SUM(base_tax_amount),0)',
            'refunded' => 'COALESCE(SUM(base_total_refunded),0)',
        ];
        $select = $conn->select()->from($table, $cols)
            ->where('created_at >= ?', $fromUtc)
            ->where('created_at <= ?', $toUtc)
            ->group('period')->order('period ASC');
        if (!$this->bool($args, 'include_canceled')) {
            $select->where('state <> ?', Mage_Sales_Model_Order::STATE_CANCELED);
        }
        if (isset($args['store_id'])) {
            $select->where('store_id = ?', $this->int($args, 'store_id'));
        }
        $rows = [];
        $totals = ['orders' => 0, 'items' => 0.0, 'revenue' => 0.0, 'subtotal' => 0.0, 'discount' => 0.0, 'shipping' => 0.0, 'tax' => 0.0, 'refunded' => 0.0];
        foreach ($conn->fetchAll($select) as $r) {
            $row = ['period' => $r['period']];
            foreach ($totals as $k => $_) {
                $row[$k] = $k === 'orders' ? (int) $r[$k] : round((float) $r[$k], 2);
                $totals[$k] += $row[$k];
            }
            $row['avg_order_value'] = $row['orders'] ? round($row['revenue'] / $row['orders'], 2) : 0;
            $rows[] = $row;
        }
        $totals['avg_order_value'] = $totals['orders'] ? round($totals['revenue'] / $totals['orders'], 2) : 0;
        foreach ($totals as $k => $v) {
            $totals[$k] = is_float($v) ? round($v, 2) : $v;
        }
        $byStatus = $conn->fetchAll(
            $conn->select()->from($table, ['status', 'orders' => 'COUNT(*)', 'revenue' => 'COALESCE(SUM(base_grand_total),0)'])
                ->where('created_at >= ?', $fromUtc)->where('created_at <= ?', $toUtc)->group('status')->order('orders DESC'),
        );
        return [
            'from' => $from,
            'to' => $to,
            'timezone' => $tz,
            'currency' => Mage::app()->getStore(isset($args['store_id']) ? $this->int($args, 'store_id') : 0)->getBaseCurrencyCode(),
            'group_by' => $groupBy,
            'rows' => $rows,
            'totals' => $totals,
            'by_status' => array_map(fn($r) => ['status' => $r['status'], 'orders' => (int) $r['orders'], 'revenue' => round((float) $r['revenue'], 2)], $byStatus),
        ];
    }

    private function utc(string $local, string $tz): string
    {
        try {
            return (new DateTime($local, new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            $this->fail('Invalid date: ' . $local);
        }
    }
}
