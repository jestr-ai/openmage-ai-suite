<?php

/**
 * OpenMage AI Suite — Core install: audit log, monthly usage, rate limiter.
 * @license MIT
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();
$conn = $installer->getConnection();

$audit = $conn->newTable($installer->getTable('ainative_core/audit'))
    ->addColumn('audit_id', Varien_Db_Ddl_Table::TYPE_BIGINT, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true])
    ->addColumn('request_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 36, ['nullable' => false])
    ->addColumn('channel', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, ['nullable' => false], 'mcp | copilot | assistant | cron')
    ->addColumn('actor_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, ['nullable' => false], 'admin | customer | guest | system')
    ->addColumn('actor_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => true])
    ->addColumn('actor_label', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, ['nullable' => true])
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => true])
    ->addColumn('kind', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, ['nullable' => false], 'tool | llm | denied | error')
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, ['nullable' => false], 'tool name or provider/model')
    ->addColumn('is_write', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('args_json', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', ['nullable' => true])
    ->addColumn('result_summary', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', ['nullable' => true])
    ->addColumn('tokens_in', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('tokens_out', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('duration_ms', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT])
    ->addIndex($installer->getIdxName('ainative_core/audit', ['created_at']), ['created_at'])
    ->addIndex($installer->getIdxName('ainative_core/audit', ['request_id']), ['request_id'])
    ->addIndex($installer->getIdxName('ainative_core/audit', ['actor_type', 'actor_id']), ['actor_type', 'actor_id'])
    ->setComment('AI Suite audit log: every tool call and LLM request');
$conn->createTable($audit);

$usage = $conn->newTable($installer->getTable('ainative_core/usage'))
    ->addColumn('usage_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true])
    ->addColumn('period', Varien_Db_Ddl_Table::TYPE_VARCHAR, 7, ['nullable' => false], 'YYYY-MM')
    ->addColumn('provider', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, ['nullable' => false])
    ->addColumn('model', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, ['nullable' => false])
    ->addColumn('channel', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, ['nullable' => false])
    ->addColumn('requests', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('tokens_in', Varien_Db_Ddl_Table::TYPE_BIGINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('tokens_out', Varien_Db_Ddl_Table::TYPE_BIGINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addIndex(
        $installer->getIdxName('ainative_core/usage', ['period', 'provider', 'model', 'channel'], Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        ['period', 'provider', 'model', 'channel'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->setComment('AI Suite monthly token usage per provider/model/channel');
$conn->createTable($usage);

$rate = $conn->newTable($installer->getTable('ainative_core/rate_limit'))
    ->addColumn('bucket', Varien_Db_Ddl_Table::TYPE_VARCHAR, 96, ['nullable' => false, 'primary' => true], 'actor key + minute')
    ->addColumn('hits', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('expires_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => false])
    ->addIndex($installer->getIdxName('ainative_core/rate_limit', ['expires_at']), ['expires_at'])
    ->setComment('AI Suite per-minute rate limit buckets');
$conn->createTable($rate);

$installer->endSetup();
