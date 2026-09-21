<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AgentLoopTest extends TestCase
{
    private AiNative_Core_Model_Tool_Context $context;

    protected function setUp(): void
    {
        Mage::getConfig()->saveConfig('ainative/general/enabled', 1);
        Mage::getConfig()->saveConfig('ainative/security/allow_write_tools', 1);
        Mage::getConfig()->reinit();
        $user = Mage::getModel('admin/user')->getCollection()->addFieldToFilter('is_active', 1)->getFirstItem();
        $this->context = AiNative_Core_Model_Tool_Context::forAdmin('copilot', $user, ['read', 'write']);
    }

    public function testScriptedToolCallRoundTrip(): void
    {
        $tools = Mage::getSingleton('ainative_core/tool_registry')->getToolsFor('admin', $this->context);
        $conv = AiNative_Core_Model_Conversation::create('s')->addUser('[[call:describe_store]]');
        $res = Mage::getModel('ainative_core/agent')->run($conv, $tools, $this->context, Mage::getModel('ainative_core/provider_mock'));
        self::assertSame(1, $res['tool_calls']);
        self::assertStringContainsString('describe_store', $res['text']);
        self::assertGreaterThan(0, $res['tokens_in']);
    }

    public function testWriteProposalsAreHeldForConfirmation(): void
    {
        $tools = Mage::getSingleton('ainative_core/tool_registry')->getToolsFor('admin', $this->context);
        self::assertArrayHasKey('update_stock', $tools);
        $conv = AiNative_Core_Model_Conversation::create('s')->addUser('[[call:update_stock {"sku":"nope","qty":1}]]');
        $agent = Mage::getModel('ainative_core/agent')->confirmWriteWith(fn() => false);
        $res = $agent->run($conv, $tools, $this->context, Mage::getModel('ainative_core/provider_mock'));
        self::assertCount(1, $res['pending_confirmations']);
        self::assertSame('update_stock', $res['pending_confirmations'][0]['name']);
    }

    public function testAuditRowIsWrittenPerToolCall(): void
    {
        $before = (int) Mage::getResourceModel('ainative_core/audit_collection')->getSize();
        $tool = Mage::getSingleton('ainative_core/tool_registry')->getTool('admin', 'describe_store');
        Mage::getSingleton('ainative_core/tool_executor')->run($tool, [], $this->context);
        self::assertSame($before + 1, (int) Mage::getResourceModel('ainative_core/audit_collection')->getSize());
    }

    public function testStorefrontScopeCannotSeeAdminTools(): void
    {
        $guest = AiNative_Core_Model_Tool_Context::forGuest('assistant', 1);
        $tools = Mage::getSingleton('ainative_core/tool_registry')->getToolsFor('storefront', $guest);
        self::assertArrayHasKey('search_catalog', $tools);
        self::assertArrayNotHasKey('get_customer', $tools);
        self::assertArrayNotHasKey('sql_readonly', $tools);
    }

    public function testTokenIssueAndVerify(): void
    {
        $user = $this->context->getAdminUser();
        $token = Mage::getModel('ainative_mcp/token');
        $secret = $token->issue('phpunit', (int) $user->getId(), 'read', ['search_products'], null);
        self::assertStringStartsWith('omg_', $secret);
        $loaded = Mage::getModel('ainative_mcp/token')->loadBySecret($secret);
        self::assertTrue($loaded->isUsable());
        self::assertSame(['search_products'], $loaded->getAllowedTools());
        self::assertFalse(Mage::getModel('ainative_mcp/token')->loadBySecret('omg_' . str_repeat('0', 40))->isUsable());
        $loaded->delete();
    }
}
