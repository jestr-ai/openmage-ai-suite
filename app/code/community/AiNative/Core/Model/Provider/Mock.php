<?php

/**
 * Deterministic offline provider for tests and demos (no network, no key).
 *
 * Behaviour:
 *  - If the last user message contains `[[call:tool_name {"json":"args"}]]`, it emits that tool call once,
 *    then on the next turn summarises the tool result.
 *  - If options.json is set, it returns a JSON object with a value for every field named in the system prompt
 *    ("keys are exactly: a, b, c").
 *  - Otherwise it echoes the last user message prefixed with "MOCK:".
 * Select it with provider code "mock" (hidden from the admin dropdown unless developer mode is on).
 * @license MIT
 */
class AiNative_Core_Model_Provider_Mock extends AiNative_Core_Model_Provider_Abstract
{
    public function getCode(): string
    {
        return 'mock';
    }

    public function getModel(): string
    {
        return 'mock-1';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function complete(AiNative_Core_Model_Conversation $conversation, array $tools = [], array $options = []): AiNative_Core_Model_Turn
    {
        $messages = $conversation->getMessages();
        $last = $messages ? $messages[count($messages) - 1] : null;
        $lastUser = '';
        foreach (array_reverse($messages) as $m) {
            if ($m['role'] === 'user') {
                $lastUser = (string) ($m['text'] ?? '');
                break;
            }
        }
        // 1) tool result just came back → summarise
        if ($last && $last['role'] === 'tool') {
            $summary = [];
            foreach ((array) $last['results'] as $r) {
                $summary[] = $r['name'] . ' → ' . mb_substr((string) $r['content'], 0, 200);
            }
            return $this->turn('MOCK summary: ' . implode(' | ', $summary), [], 'end_turn');
        }
        // 2) scripted tool call
        if (preg_match('/\[\[call:([a-z_]+)\s*(\{.*?\})?\]\]/s', $lastUser, $m)) {
            $args = isset($m[2]) ? (json_decode($m[2], true) ?: []) : [];
            $available = array_map(fn($t) => $t['name'], $this->normalizeTools($tools));
            if (!in_array($m[1], $available, true)) {
                return $this->turn('MOCK: tool ' . $m[1] . ' is not available. Available: ' . implode(', ', $available), [], 'end_turn');
            }
            return $this->turn('Calling ' . $m[1] . '.', [new AiNative_Core_Model_ToolCall($this->newCallId(), $m[1], $args)], 'tool_use');
        }
        // 3) JSON field generation
        if (!empty($options['json']) && preg_match('/keys are exactly:\s*([a-z_,\s]+)\./i', $conversation->getSystem(), $km)) {
            $fields = array_filter(array_map('trim', explode(',', $km[1])));
            $out = [];
            foreach ($fields as $f) {
                $out[$f] = 'MOCK ' . $f . ' generated at ' . date('H:i:s');
            }
            return $this->turn(json_encode($out) ?: '{}', [], 'end_turn');
        }
        return $this->turn('MOCK: ' . $lastUser, [], 'end_turn');
    }

    private function turn(string $text, array $calls, string $stop): AiNative_Core_Model_Turn
    {
        return new AiNative_Core_Model_Turn($text, $calls, (int) (mb_strlen($text) / 4) + 10, (int) (mb_strlen($text) / 4), $stop, $this->getModel(), ['mock' => true]);
    }
}
