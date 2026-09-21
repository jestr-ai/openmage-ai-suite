<?php

/**
 * Google Gemini generateContent (raw HTTP). Function calling via functionDeclarations / functionCall / functionResponse.
 * @license MIT
 */
class AiNative_Core_Model_Provider_Gemini extends AiNative_Core_Model_Provider_Abstract
{
    public function getCode(): string
    {
        return 'gemini';
    }

    public function complete(AiNative_Core_Model_Conversation $conversation, array $tools = [], array $options = []): AiNative_Core_Model_Turn
    {
        $helper = $this->helper();
        $model = $this->getModel();

        // Gemini has no call ids: map our ids to names when replaying results.
        $contents = [];
        foreach ($conversation->getMessages() as $m) {
            switch ($m['role']) {
                case 'user':
                    $contents[] = ['role' => 'user', 'parts' => [['text' => (string) $m['text']]]];
                    break;
                case 'assistant':
                    $parts = [];
                    if (($m['text'] ?? '') !== '' && $m['text'] !== null) {
                        $parts[] = ['text' => (string) $m['text']];
                    }
                    foreach ((array) ($m['tool_calls'] ?? []) as $call) {
                        $parts[] = ['functionCall' => ['name' => (string) $call['name'], 'args' => (object) ($call['arguments'] ?? [])]];
                    }
                    if ($parts) {
                        $contents[] = ['role' => 'model', 'parts' => $parts];
                    }
                    break;
                case 'tool':
                    $parts = [];
                    foreach ((array) ($m['results'] ?? []) as $r) {
                        $decoded = json_decode((string) $r['content'], true);
                        $payload = is_array($decoded) ? $decoded : ['result' => (string) $r['content']];
                        if (!empty($r['is_error'])) {
                            $payload = ['error' => (string) $r['content']];
                        }
                        $parts[] = ['functionResponse' => ['name' => (string) $r['name'], 'response' => (object) $payload]];
                    }
                    if ($parts) {
                        $contents[] = ['role' => 'user', 'parts' => $parts];
                    }
                    break;
            }
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => (int) ($options['max_tokens'] ?? $helper->getMaxTokens($this->storeId)),
                'temperature' => (float) ($options['temperature'] ?? $helper->getTemperature($this->storeId)),
            ],
        ];
        if (!empty($options['json'])) {
            $body['generationConfig']['responseMimeType'] = 'application/json';
        }
        if ($conversation->getSystem() !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $conversation->getSystem()]]];
        }
        $normalized = $this->normalizeTools($tools);
        if ($normalized) {
            $body['tools'] = [[
                'functionDeclarations' => array_map(fn(array $t) => [
                    'name' => $t['name'],
                    'description' => $t['description'],
                    'parameters' => $this->sanitizeSchema($t['input_schema'], ['additionalProperties', '$schema', 'default', 'examples']),
                ], $normalized),
            ]];
        }

        $url = sprintf('%s/models/%s:generateContent', $this->getBaseUrl(), rawurlencode($model));
        $response = $this->postJson($url, $body, ['x-goog-api-key' => $this->getApiKey()]);

        $candidate = (array) (($response['candidates'] ?? [])[0] ?? []);
        $text = '';
        $calls = [];
        foreach ((array) (($candidate['content'] ?? [])['parts'] ?? []) as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            } elseif (isset($part['functionCall'])) {
                $fc = (array) $part['functionCall'];
                $calls[] = new AiNative_Core_Model_ToolCall(
                    $this->newCallId(),
                    (string) ($fc['name'] ?? ''),
                    $this->decodeArguments($fc['args'] ?? []),
                );
            }
        }
        $usage = (array) ($response['usageMetadata'] ?? []);
        $finish = (string) ($candidate['finishReason'] ?? 'STOP');
        $stop = match ($finish) {
            'SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII' => 'refusal',
            'MAX_TOKENS' => 'max_tokens',
            default => $calls ? 'tool_use' : 'end_turn',
        };
        return new AiNative_Core_Model_Turn(
            $text === '' ? null : $text,
            $calls,
            (int) ($usage['promptTokenCount'] ?? 0),
            (int) ($usage['candidatesTokenCount'] ?? 0),
            $stop,
            (string) ($response['modelVersion'] ?? $model),
            $response,
        );
    }
}
