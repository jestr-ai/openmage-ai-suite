<?php

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();
$conn = $installer->getConnection();

$job = $conn->newTable($installer->getTable('ainative_copilot/job'))
    ->addColumn('job_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true])
    ->addColumn('type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, ['nullable' => false], 'product_content | category_content')
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false])
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('fields', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, ['nullable' => false], 'comma list of fields to generate')
    ->addColumn('options_json', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', ['nullable' => true])
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, ['nullable' => false, 'default' => 'pending'], 'pending | running | draft | applied | rejected | failed')
    ->addColumn('draft_json', Varien_Db_Ddl_Table::TYPE_TEXT, '2M', ['nullable' => true])
    ->addColumn('error', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', ['nullable' => true])
    ->addColumn('tokens_in', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('tokens_out', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('created_by', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => true])
    ->addColumn('reviewed_by', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => true])
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT])
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => true])
    ->addIndex($installer->getIdxName('ainative_copilot/job', ['status']), ['status'])
    ->addIndex($installer->getIdxName('ainative_copilot/job', ['type', 'entity_id']), ['type', 'entity_id'])
    ->setComment('AI Suite bulk content jobs and drafts');
$conn->createTable($job);

$conversation = $conn->newTable($installer->getTable('ainative_copilot/conversation'))
    ->addColumn('conversation_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true])
    ->addColumn('channel', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, ['nullable' => false], 'copilot | assistant')
    ->addColumn('actor_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, ['nullable' => false])
    ->addColumn('actor_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => true])
    ->addColumn('session_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, ['nullable' => true], 'opaque per-browser key (storefront)')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('title', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, ['nullable' => true])
    ->addColumn('state_json', Varien_Db_Ddl_Table::TYPE_TEXT, '2M', ['nullable' => true], 'provider-neutral message history')
    ->addColumn('messages_count', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('tokens_in', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('tokens_out', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('meta_json', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', ['nullable' => true], 'analytics: cards shown, add_to_cart, handoff…')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT])
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => true])
    ->addIndex($installer->getIdxName('ainative_copilot/conversation', ['channel', 'actor_type', 'actor_id']), ['channel', 'actor_type', 'actor_id'])
    ->addIndex($installer->getIdxName('ainative_copilot/conversation', ['session_key']), ['session_key'])
    ->addIndex($installer->getIdxName('ainative_copilot/conversation', ['updated_at']), ['updated_at'])
    ->setComment('AI Suite conversations (admin Ask and storefront Assistant)');
$conn->createTable($conversation);

$message = $conn->newTable($installer->getTable('ainative_copilot/message'))
    ->addColumn('message_id', Varien_Db_Ddl_Table::TYPE_BIGINT, null, ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true])
    ->addColumn('conversation_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false])
    ->addColumn('role', Varien_Db_Ddl_Table::TYPE_VARCHAR, 16, ['nullable' => false], 'user | assistant')
    ->addColumn('content', Varien_Db_Ddl_Table::TYPE_TEXT, '2M', ['nullable' => true])
    ->addColumn('cards_json', Varien_Db_Ddl_Table::TYPE_TEXT, '2M', ['nullable' => true], 'structured UI cards')
    ->addColumn('tool_calls', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('tokens_in', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('tokens_out', Varien_Db_Ddl_Table::TYPE_INTEGER, null, ['unsigned' => true, 'nullable' => false, 'default' => 0])
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, ['nullable' => false, 'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT])
    ->addIndex($installer->getIdxName('ainative_copilot/message', ['conversation_id']), ['conversation_id'])
    ->addForeignKey(
        $installer->getFkName('ainative_copilot/message', 'conversation_id', 'ainative_copilot/conversation', 'conversation_id'),
        'conversation_id',
        $installer->getTable('ainative_copilot/conversation'),
        'conversation_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('AI Suite conversation messages');
$conn->createTable($message);

$installer->endSetup();
