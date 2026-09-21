<?php

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();
$conn = $installer->getConnection();

$table = $conn->newTable($installer->getTable('ainative_mcp/token'))
    ->addColumn('token_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true])
    ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, 128, ['nullable' => false])
    ->addColumn('admin_user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false])
    ->addColumn('token_hash', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, ['nullable' => false])
    ->addColumn('token_prefix', Varien_Db_Ddl_Table::TYPE_VARCHAR, 12, ['nullable' => false])
    ->addColumn('scope', Varien_Db_Ddl_Table::TYPE_VARCHAR, 8, ['nullable' => false, 'default' => 'read'], 'read | write')
    ->addColumn('allowed_tools', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', ['nullable' => true], 'JSON list or NULL = all')
    ->addColumn('is_active', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 1])
    ->addColumn('expires_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => true])
    ->addColumn('last_used_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => true])
    ->addColumn('last_used_ip', Varien_Db_Ddl_Table::TYPE_VARCHAR, 45, ['nullable' => true])
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT])
    ->addIndex($installer->getIdxName('ainative_mcp/token', ['token_hash'], Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE), ['token_hash'], ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE])
    ->addIndex($installer->getIdxName('ainative_mcp/token', ['admin_user_id']), ['admin_user_id'])
    ->addForeignKey(
        $installer->getFkName('ainative_mcp/token', 'admin_user_id', 'admin/user', 'user_id'),
        'admin_user_id',
        $installer->getTable('admin/user'),
        'user_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('AI Suite MCP access tokens (hashed)');
$conn->createTable($table);

$installer->endSetup();
