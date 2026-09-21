<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Runs the JSON-RPC server in-process against the installed store (needs DB + sample data).
 */
final class McpServerTest extends TestCase
{
    private AiNative_Mcp_Model_Server $server;

    protected function setUp(): void
    {
        Mage::getConfig()->saveConfig('ainative/general/enabled', 1);
        Mage::getConfig()->saveConfig('ainative_mcp/sql/enabled', 1);
        Mage::getConfig()->reinit();
        $user = Mage::getModel('admin/user')->getCollection()->addFieldToFilter('is_active', 1)->getFirstItem();
        self::assertNotEmpty($user->getId(), 'needs an admin user');
        $context = AiNative_Core_Model_Tool_Context::forAdmin('mcp', $user, ['read']);
        $this->server = Mage::getModel('ainative_mcp/server', ['context' => $context]);
    }

    private function call(string $method, array $params = []): array
    {
        $r = $this->server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
        self::assertIsArray($r);
        return $r;
    }

    public function testInitializeNegotiatesVersion(): void
    {
        $r = $this->call('initialize', ['protocolVersion' => '2025-03-26']);
        self::assertSame('2025-03-26', $r['result']['protocolVersion']);
        $r = $this->call('initialize', ['protocolVersion' => '1999-01-01']);
        self::assertSame(AiNative_Mcp_Model_Server::LATEST_VERSION, $r['result']['protocolVersion']);
    }

    public function testNotificationsProduceNoResponse(): void
    {
        self::assertNull($this->server->handle(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
    }

    public function testReadTokenSeesNoWriteTools(): void
    {
        $tools = $this->call('tools/list')['result']['tools'];
        $names = array_column($tools, 'name');
        self::assertContains('search_products', $names);
        self::assertNotContains('update_stock', $names);
        foreach ($tools as $t) {
            self::assertTrue($t['annotations']['readOnlyHint']);
        }
    }

    public function testWriteToolCallIsRefusedWithExplanation(): void
    {
        $r = $this->call('tools/call', ['name' => 'update_stock', 'arguments' => ['sku' => 'x', 'qty' => 1]]);
        self::assertTrue($r['result']['isError']);
        self::assertStringContainsString('write', strtolower($r['result']['content'][0]['text']));
    }

    public function testUnknownToolIsInvalidParams(): void
    {
        $r = $this->call('tools/call', ['name' => 'nope']);
        self::assertSame(AiNative_Mcp_Model_Server::ERR_INVALID_PARAMS, $r['error']['code']);
    }

    public function testDescribeStoreAndSearchProducts(): void
    {
        $r = $this->call('tools/call', ['name' => 'describe_store', 'arguments' => []]);
        self::assertFalse($r['result']['isError']);
        self::assertArrayHasKey('websites', $r['result']['structuredContent']);
        $r = $this->call('tools/call', ['name' => 'search_products', 'arguments' => ['query' => 'a', 'limit' => 2]]);
        self::assertFalse($r['result']['isError']);
        self::assertLessThanOrEqual(2, count($r['result']['structuredContent']['items']));
    }

    public function testSqlGuards(): void
    {
        foreach (['UPDATE core_config_data SET value=1', 'SELECT * FROM admin_user', 'SELECT 1; SELECT 2', 'SELECT SLEEP(1)', 'SELECT * FROM information_schema.tables'] as $sql) {
            $r = $this->call('tools/call', ['name' => 'sql_readonly', 'arguments' => ['sql' => $sql]]);
            self::assertTrue($r['result']['isError'], $sql);
        }
        $r = $this->call('tools/call', ['name' => 'sql_readonly', 'arguments' => ['sql' => 'SELECT entity_id FROM catalog_product_entity']]);
        self::assertFalse($r['result']['isError']);
        self::assertStringContainsString('LIMIT', $r['result']['structuredContent']['executed_sql']);
    }

    public function testGetConfigHidesSecrets(): void
    {
        $r = $this->call('tools/call', ['name' => 'get_config', 'arguments' => ['path' => 'payment/paypal']]);
        self::assertTrue($r['result']['isError']);
        $r = $this->call('tools/call', ['name' => 'get_config', 'arguments' => ['path' => 'general/store_information']]);
        self::assertFalse($r['result']['isError']);
    }

    public function testPromptsAndResources(): void
    {
        self::assertNotEmpty($this->call('prompts/list')['result']['prompts']);
        $r = $this->call('prompts/get', ['name' => 'order_lookup', 'arguments' => ['order_number' => '42']]);
        self::assertStringContainsString('42', $r['result']['messages'][0]['content']['text']);
        $r = $this->call('resources/read', ['uri' => 'openmage://order-statuses']);
        self::assertSame('application/json', $r['result']['contents'][0]['mimeType']);
    }
}
