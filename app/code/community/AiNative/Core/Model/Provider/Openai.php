<?php

/**
 * OpenAI Chat Completions (raw HTTP). Also works with any OpenAI-compatible server (Ollama, vLLM, LM Studio…).
 * @license MIT
 */
class AiNative_Core_Model_Provider_Openai extends AiNative_Core_Model_Provider_Abstract
{
    public function getCode(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        // Local OpenAI-compatible servers usually need no key.
        return $this->getModel() !== '' && ($this->getApiKey() !== '' || !str_contains($this->getBaseUrl(), 'api.openai.com'));
    }

    public function complete(AiNative_Core_Model_Conversation $conversation, array $tools = [], array $options = []): AiNative_Core_Model_Turn
    {
        $helper = $this->helper();
        $model = $this->getModel();
        $messages = [];
        if ($conversation->getSystem() !== '') {
            $messages[] = ['role' => 'system', 'content' => $conversation->getSystem()];
        }
        foreach ($conversation->getMessages() as $m) {
            switch ($m['role']) {
                case 'user':
                    $messages[] = ['role' => 'user', 'content' => (string) $m['text']];
                    break;
                case 'assistant':
                    $msg = ['role' => 'assistant', 'content' => ($m['text'] ?? null) === '' ? null : ($m['text'] ?? null)];
                    $calls = [];
                    foreach ((array) ($m['tool_calls'] ?? []) as $call) {
                        $calls[] = [
                            'id' => (string) $call['id'],
                            'type' => 'function',
                            'function' => [
                                'name' => (string) $call['name'],
                                'arguments' => json_encode((object) ($call['arguments'] ?? []), JSON_UNESCAPED_UNICODE),
                            ],
                        ];
                    }
                    if ($calls) {
                        $msg['tool_calls'] = $calls;
                    }
                    $messages[] = $msg;
                    break;
                case 'tool':
                    foreach ((array) ($m['results'] ?? []) as $r) {
                        $messages[] = ['role' => 'tool', 'tool_call_id' => (string) $r['id'], 'content' => (string) $r['content']];
                    }
                    break;
            }
        }

        $body = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => (int) ($options['max_tokens'] ?? $helper->getMaxTokens($this->storeId)),
        ];
        $temperature = $options['temperature'] ?? $helper->getTemperature($this->storeId);
        if ($temperature !== null && !$this->isReasoningModel($model)) {
            $body['temperature'] = (float) $temperature;
        }
        if (!empty($options['json'])) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        $normalized = $this->normalizeTools($tools);
        if ($normalized) {
            $body['tools'] = array_map(fn(array $t) => [
                'type' => 'function',
                'function' => [
                    'name' => $t['name'],
                    'description' => $t['description'],
                    'parameters' => $t['input_schema'],
                ],
            ], $normalized);
            $body['tool_choice'] = 'auto';
        }

        $headers = [];
        if ($this->getApiKey() !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->getApiKey();
        }
        $response = $this->postJson($this->getBaseUrl() . '/chat/completions', $body, $headers);

        $choice = (array) (($response['choices'] ?? [])[0] ?? []);
        $message = (array) ($choice['message'] ?? []);
        $calls = [];
        foreach ((array) ($message['tool_calls'] ?? []) as $call) {
            $fn = (array) ($call['function'] ?? []);
            $calls[] = new AiNative_Core_Model_ToolCall(
                (string) ($call['id'] ?? $this->newCallId()),
                (string) ($fn['name'] ?? ''),
                $this->decodeArguments($fn['arguments'] ?? '{}'),
            );
        }
        $text = $message['content'] ?? null;
        if (is_array($text)) {
            $text = implode('', array_map(fn($p) => (string) ($p['text'] ?? ''), $text));
        }
        $usage = (array) ($response['usage'] ?? []);
        $finish = (string) ($choice['finish_reason'] ?? 'stop');
        return new AiNative_Core_Model_Turn(
            ($text === null || $text === '') ? null : (string) $text,
            $calls,
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
            $finish === 'content_filter' ? 'refusal' : $finish,
            (string) ($response['model'] ?? $model),
            $response,
        );
    }

    private function isReasoningModel(string $model): bool
    {
        return (bool) preg_match('/^(o\d|gpt-5)/', $model);
    }
}
