<?php

/**
 * Guarded read-only SQL: single SELECT, denied-table check, forced LIMIT, statement timeout, read connection.
 * Disabled unless ainative_mcp/sql/enabled = 1.
 */
class AiNative_Mcp_Model_Tool_Store_SqlReadonly extends AiNative_Core_Model_Tool_Abstract
{
    private const FORBIDDEN = '/\b(insert|update|delete|replace|drop|alter|create|truncate|rename|grant|revoke|lock|unlock|call|execute|exec|prepare|deallocate|handler|load|outfile|dumpfile|into|set|use|show|describe|explain|analyze|optimize|repair|flush|kill|shutdown|sleep|benchmark|get_lock|release_lock|load_file|information_schema|performance_schema|mysql\.|sys\.)\b/i';

    public function getName(): string
    {
        return 'sql_readonly';
    }

    public function getDescription(): string
    {
        return 'Run ONE read-only SELECT against the store database (MySQL/MariaDB) for questions the other tools cannot answer. Rules: SELECT only, single statement, no subqueries into sensitive tables, LIMIT enforced (max ' . Mage::helper('ainative_mcp')->getSqlMaxRows() . ' rows), 5s timeout. Table names use the store prefix automatically if you write them without prefix. Key tables: catalog_product_entity, catalog_product_entity_varchar/decimal/int/text (EAV, attribute_id from eav_attribute), catalog_category_entity, cataloginventory_stock_item, sales_flat_order, sales_flat_order_item, sales_flat_order_address, sales_flat_quote, sales_flat_quote_item, salesrule, cms_page, core_store, core_website, eav_attribute, eav_attribute_option_value, catalog_product_index_price, catalog_category_product, review, rating_option_vote. Denied: admin/oauth/api/session/config/customer/payment tables. Prefer the purpose-built tools when they fit.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'sql' => ['type' => 'string', 'maxLength' => 8000],
        ], ['sql']);
    }

    public function getAclResource(): ?string
    {
        return 'admin/system';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $helper = Mage::helper('ainative_mcp');
        if (!$helper->isSqlEnabled()) {
            $this->fail('The sql_readonly tool is disabled. Enable it under AI Suite > MCP Server > Read-only SQL Tool.');
        }
        $sql = trim($this->str($args, 'sql'));
        $sql = rtrim($sql, "; \t\n\r");
        $sql = preg_replace('~/\*.*?\*/~s', ' ', $sql) ?? $sql;
        $sql = preg_replace('~(--|#)[^\n]*~', ' ', $sql) ?? $sql;
        $sql = trim($sql);
        if (!preg_match('/^select\s/i', $sql)) {
            $this->fail('Only SELECT statements are allowed.');
        }
        if (str_contains($sql, ';')) {
            $this->fail('Only a single statement is allowed.');
        }
        $stripped = preg_replace("/'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"/", "''", $sql) ?? $sql;
        if (preg_match(self::FORBIDDEN, $stripped, $m)) {
            $this->fail(sprintf('Forbidden keyword "%s".', $m[1]));
        }
        // table references
        preg_match_all('/\b(?:from|join)\s+`?([a-z0-9_]+)`?/i', $stripped, $tables);
        $denied = $helper->getSqlDeniedTables();
        $prefix = (string) Mage::getConfig()->getTablePrefix();
        foreach (array_unique($tables[1] ?? []) as $table) {
            $bare = $prefix !== '' && str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
            foreach ($denied as $d) {
                if ($d !== '' && str_starts_with($bare, $d)) {
                    $this->fail(sprintf('Table "%s" is not queryable via AI.', $table));
                }
            }
            if ($prefix !== '' && !str_starts_with($table, $prefix)) {
                $sql = preg_replace('/\b(from|join)\s+`?' . preg_quote($table, '/') . '`?/i', '$1 `' . $prefix . $table . '`', $sql) ?? $sql;
            }
        }
        $max = $helper->getSqlMaxRows();
        if (preg_match('/\blimit\s+(\d+)(?:\s*,\s*(\d+))?\s*$/i', $stripped, $lm)) {
            $n = isset($lm[2]) ? (int) $lm[2] : (int) $lm[1];
            if ($n > $max) {
                $sql = preg_replace('/\blimit\s+\d+(\s*,\s*\d+)?\s*$/i', 'LIMIT ' . $max, $sql) ?? $sql;
            }
        } else {
            $sql .= ' LIMIT ' . $max;
        }
        $sql = preg_replace('/^select\s/i', 'SELECT /*+ MAX_EXECUTION_TIME(5000) */ ', $sql, 1) ?? $sql;

        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $started = microtime(true);
        try {
            $conn->query('SET SESSION max_statement_time = 5');
        } catch (Throwable) {
            // MySQL (not MariaDB): the optimizer hint handles it
        }
        try {
            $rows = $conn->fetchAll($sql);
        } catch (Throwable $e) {
            $this->fail('SQL error: ' . preg_replace('/SQLSTATE\[\w+\]: /', '', $e->getMessage()));
        }
        Mage::helper('ainative_core')->log(sprintf('sql_readonly by %s: %s', $context->getActorLabel(), $sql));
        return [
            'rows' => $rows,
            'row_count' => count($rows),
            'truncated' => count($rows) >= $max,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            'executed_sql' => $sql,
        ];
    }
}
