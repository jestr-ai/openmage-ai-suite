<?php

/**
 * Anthropic Messages API (raw HTTP). Tool use via tool_use / tool_result blocks.
 * @license MIT
 */
class AiNative_Core_Model_Provider_Anthropic extends AiNative_Core_Model_Provider_Abstract
{
    public const API_VERSION = '2023-06-01';

    public function getCode(): string
    {
        return 'anthropic';
    }

    public function complete(AiNative_Core_Model_Conversation $conversation, array $tools = [], array $options = []): AiNative_Core_Model_Turn
    {
        $helper = $this->helper();
        $model = $this->getModel();
        $body = [
            'model' => $model,
            'max_tokens' => (int) ($options['max_tokens'] ?? $helper->getMaxTokens($this->storeId)),
            'messages' => $this->buildMessages($conversation),
        ];
        if ($conversation->getSystem() !== '') {
            $body['system'] = $conversation->getSystem();
        }
        $effort = $this->config('effort');
        if ($effort !== '' && $this->supportsEffort($model)) {
            $body['output_config'] = ['effort' => $effort];
        }
        if (!$this->supportsEffort($model) && isset($options['temperature'])) {
            // Sampling params are rejected on Claude 4.7+; only send to older models.
            $body['temperature'] = (float) $options['temperature'];
        }
        $normalized = $this->normalizeTools($tools);
        if ($normalized) {
            $body['tools'] = array_map(fn(array $t) => [
                'name' => $t['name'],
                'description' => $t['description'],
                'input_schema' => $t['input_schema'],
            ], $normalized);
        }

        $response = $this->postJson($this->getBaseUrl() . '/v1/messages', $body, [
            'x-api-key' => $this->getApiKey(),
            'anthropic-version' => self::API_VERSION,
        ]);

        $text = '';
        $calls = [];
        foreach ((array) ($response['content'] ?? []) as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $text .= (string) ($block['text'] ?? '');
            } elseif ($type === 'tool_use') {
                $calls[] = new AiNative_Core_Model_ToolCall(
                    (string) ($block['id'] ?? $this->newCallId()),
                    (string) ($block['name'] ?? ''),
                    $this->decodeArguments($block['input'] ?? []),
                );
            }
        }
        $usage = (array) ($response['usage'] ?? []);
        return new AiNative_Core_Model_Turn(
            $text === '' ? null : $text,
            $calls,
            (int) ($usage['input_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            (string) ($response['stop_reason'] ?? 'end_turn'),
            (string) ($response['model'] ?? $model),
            $response,
        );
    }

    private function supportsEffort(string $model): bool
    {
        // effort exists on Claude 4.6+ (opus-4-6, sonnet-4-6, opus-4-7/4-8, *-5, fable). Haiku 4.5 and older do not.
        return (bool) preg_match('/(opus-4-[6-9]|sonnet-4-[6-9]|-5\b|-5-|fable|mythos)/', $model);
    }

    private function buildMessages(AiNative_Core_Model_Conversation $conversation): array
    {
        $out = [];
        foreach ($conversation->getMessages() as $m) {
            switch ($m['role']) {
                case 'user':
                    $out[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => (string) $m['text']]]];
                    break;
                case 'assistant':
                    $content = [];
                    if (isset($m['text']) && $m['text'] !== '' && $m['text'] !== null) {
                        $content[] = ['type' => 'text', 'text' => (string) $m['text']];
                    }
                    foreach ((array) ($m['tool_calls'] ?? []) as $call) {
                        $content[] = [
                            'type' => 'tool_use',
                            'id' => (string) $call['id'],
                            'name' => (string) $call['name'],
                            'input' => (object) ($call['arguments'] ?? []),
                        ];
                    }
                    if ($content) {
                        $out[] = ['role' => 'assistant', 'content' => $content];
                    }
                    break;
                case 'tool':
                    $content = [];
                    foreach ((array) ($m['results'] ?? []) as $r) {
                        $block = [
                            'type' => 'tool_result',
                            'tool_use_id' => (string) $r['id'],
                            'content' => (string) $r['content'],
                        ];
                        if (!empty($r['is_error'])) {
                            $block['is_error'] = true;
                        }
                        $content[] = $block;
                    }
                    if ($content) {
                        $out[] = ['role' => 'user', 'content' => $content];
                    }
                    break;
            }
        }
        return $out;
    }
}
