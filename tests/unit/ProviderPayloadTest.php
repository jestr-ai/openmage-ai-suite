<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

trait AiNativeCapturesPost
{
    public array $captured = [];
    public array $response = [];

    public function getModel(): string
    {
        return 'test-model';
    }

    protected function getApiKey(): string
    {
        return 'k';
    }

    protected function getBaseUrl(): string
    {
        return 'https://example.invalid';
    }

    protected function config(string $key): string
    {
        return $key === 'effort' ? 'low' : '';
    }

    protected function postJson(string $url, array $body, array $headers): array
    {
        $this->captured = ['url' => $url, 'body' => $body, 'headers' => $headers];
        return $this->response;
    }
}

final class AiNativeAnthropicDouble extends AiNative_Core_Model_Provider_Anthropic
{
    use AiNativeCapturesPost;
}
final class AiNativeOpenaiDouble extends AiNative_Core_Model_Provider_Openai
{
    use AiNativeCapturesPost;
}
final class AiNativeGeminiDouble extends AiNative_Core_Model_Provider_Gemini
{
    use AiNativeCapturesPost;
}

/**
 * Verifies each adapter builds the wire format its API expects and parses the reply, without network.
 */
final class ProviderPayloadTest extends TestCase
{
    private function conversation(): AiNative_Core_Model_Conversation
    {
        return AiNative_Core_Model_Conversation::create('You are a test.')
            ->addUser('hi')
            ->addAssistant(null, [['id' => 'call_1', 'name' => 'get_stock', 'arguments' => ['skus' => ['a']]]])
            ->addToolResults([['id' => 'call_1', 'name' => 'get_stock', 'content' => '{"qty":1}', 'is_error' => false]]);
    }

    private function tool(): array
    {
        return ['name' => 'get_stock', 'description' => 'd', 'input_schema' => ['type' => 'object', 'properties' => ['skus' => ['type' => 'array', 'items' => ['type' => 'string']]], 'additionalProperties' => false]];
    }

    public function testAnthropicWireFormat(): void
    {
        $p = new AiNativeAnthropicDouble();
        $p->response = ['content' => [['type' => 'text', 'text' => 'ok'], ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_stock', 'input' => ['skus' => ['b']]]], 'stop_reason' => 'tool_use', 'usage' => ['input_tokens' => 5, 'output_tokens' => 7], 'model' => 'claude-x'];
        $turn = $p->complete($this->conversation(), [$this->tool()], ['max_tokens' => 100]);
        $b = $p->captured['body'];
        self::assertSame('https://example.invalid/v1/messages', $p->captured['url']);
        self::assertSame('2023-06-01', $p->captured['headers']['anthropic-version']);
        self::assertSame('k', $p->captured['headers']['x-api-key']);
        self::assertSame('You are a test.', $b['system']);
        self::assertSame(100, $b['max_tokens']);
        self::assertSame('tool_use', $b['messages'][1]['content'][0]['type']);
        self::assertSame('tool_result', $b['messages'][2]['content'][0]['type']);
        self::assertSame('call_1', $b['messages'][2]['content'][0]['tool_use_id']);
        self::assertSame('get_stock', $b['tools'][0]['name']);
        self::assertArrayHasKey('input_schema', $b['tools'][0]);
        self::assertArrayNotHasKey('temperature', $b, 'sampling params are rejected by current Claude models');
        self::assertArrayNotHasKey('output_config', $b, 'effort only for models that support it; test-model does not match');
        self::assertSame('ok', $turn->getText());
        self::assertSame('toolu_1', $turn->getToolCalls()[0]->id);
        self::assertSame(['skus' => ['b']], $turn->getToolCalls()[0]->arguments);
        self::assertSame(12, $turn->getTokensIn() + $turn->getTokensOut());
        self::assertSame('claude-x', $turn->getModel());
    }

    public function testOpenaiWireFormat(): void
    {
        $p = new AiNativeOpenaiDouble();
        $p->response = ['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'call_9', 'type' => 'function', 'function' => ['name' => 'get_stock', 'arguments' => '{"skus":["c"]}']]]], 'finish_reason' => 'tool_calls']], 'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 4]];
        $turn = $p->complete($this->conversation(), [$this->tool()], ['temperature' => 0.2, 'max_tokens' => 50]);
        $b = $p->captured['body'];
        self::assertSame('https://example.invalid/chat/completions', $p->captured['url']);
        self::assertSame('Bearer k', $p->captured['headers']['Authorization']);
        self::assertSame('system', $b['messages'][0]['role']);
        self::assertSame('assistant', $b['messages'][2]['role']);
        self::assertSame('call_1', $b['messages'][2]['tool_calls'][0]['id']);
        self::assertSame('tool', $b['messages'][3]['role']);
        self::assertSame('call_1', $b['messages'][3]['tool_call_id']);
        self::assertSame('function', $b['tools'][0]['type']);
        self::assertSame('auto', $b['tool_choice']);
        self::assertSame(0.2, $b['temperature']);
        self::assertSame(50, $b['max_completion_tokens']);
        self::assertSame(['skus' => ['c']], $turn->getToolCalls()[0]->arguments);
        self::assertTrue($turn->hasToolCalls());
        self::assertNull($turn->getText());
    }

    public function testEmptyToolSchemaEncodesAsJsonObject(): void
    {
        $p = new AiNativeAnthropicDouble();
        $p->response = ['content' => [['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn', 'usage' => []];
        $p->complete($this->conversation(), [['name' => 'describe_store', 'description' => 'd', 'input_schema' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false]]]);
        $json = json_encode($p->captured['body']['tools'][0]['input_schema']);
        self::assertStringContainsString('"properties":{}', $json, 'the Anthropic API rejects "properties":[]');
    }

    public function testOpenaiContentFilterMapsToRefusal(): void
    {
        $p = new AiNativeOpenaiDouble();
        $p->response = ['choices' => [['message' => ['content' => 'no'], 'finish_reason' => 'content_filter']], 'usage' => []];
        self::assertTrue($p->complete($this->conversation())->isRefusal());
    }

    public function testGeminiWireFormatAndSchemaSanitising(): void
    {
        $p = new AiNativeGeminiDouble();
        $p->response = ['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => 'get_stock', 'args' => ['skus' => ['d']]]]]], 'finishReason' => 'STOP']], 'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 2]];
        $turn = $p->complete($this->conversation(), [$this->tool()]);
        $b = $p->captured['body'];
        self::assertStringContainsString('/models/test-model:generateContent', $p->captured['url']);
        self::assertSame('k', $p->captured['headers']['x-goog-api-key']);
        self::assertSame('You are a test.', $b['systemInstruction']['parts'][0]['text']);
        self::assertSame('model', $b['contents'][1]['role']);
        self::assertArrayHasKey('functionCall', $b['contents'][1]['parts'][0]);
        self::assertArrayHasKey('functionResponse', $b['contents'][2]['parts'][0]);
        self::assertSame('get_stock', $b['contents'][2]['parts'][0]['functionResponse']['name']);
        $params = $b['tools'][0]['functionDeclarations'][0]['parameters'];
        self::assertArrayNotHasKey('additionalProperties', $params);
        self::assertSame('tool_use', $turn->getStopReason());
        self::assertSame(['skus' => ['d']], $turn->getToolCalls()[0]->arguments);
        self::assertNotEmpty($turn->getToolCalls()[0]->id, 'Gemini has no call ids; adapter must synthesise one');
    }

    public function testGeminiSafetyBlockIsRefusal(): void
    {
        $p = new AiNativeGeminiDouble();
        $p->response = ['candidates' => [['content' => ['parts' => []], 'finishReason' => 'SAFETY']], 'usageMetadata' => []];
        self::assertTrue($p->complete($this->conversation())->isRefusal());
    }
}
