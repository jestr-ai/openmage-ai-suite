<?php

/**
 * Provider-neutral message list.
 *
 * Messages:
 *   ['role' => 'user',      'text' => string]
 *   ['role' => 'assistant', 'text' => ?string, 'tool_calls' => list<array{id:string,name:string,arguments:array}>]
 *   ['role' => 'tool',      'results' => list<array{id:string,name:string,content:string,is_error:bool}>]
 *
 * @license MIT
 */
class AiNative_Core_Model_Conversation
{
    private string $system = '';

    /** @var list<array<string, mixed>> */
    private array $messages = [];

    public static function create(string $system = ''): self
    {
        $c = new self();
        $c->system = $system;
        return $c;
    }

    public function setSystem(string $system): self
    {
        $this->system = $system;
        return $this;
    }

    public function getSystem(): string
    {
        return $this->system;
    }

    public function addUser(string $text): self
    {
        $this->messages[] = ['role' => 'user', 'text' => $text];
        return $this;
    }

    /**
     * @param list<array{id:string,name:string,arguments:array}> $toolCalls
     */
    public function addAssistant(?string $text, array $toolCalls = []): self
    {
        $this->messages[] = ['role' => 'assistant', 'text' => $text, 'tool_calls' => $toolCalls];
        return $this;
    }

    public function addTurn(AiNative_Core_Model_Turn $turn): self
    {
        return $this->addAssistant($turn->getText(), $turn->getToolCallsAsArray());
    }

    /**
     * @param list<array{id:string,name:string,content:string,is_error:bool}> $results
     */
    public function addToolResults(array $results): self
    {
        $this->messages[] = ['role' => 'tool', 'results' => $results];
        return $this;
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    public function setMessages(array $messages): self
    {
        $this->messages = array_values($messages);
        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Keep the last N messages (never splitting an assistant tool-call from its results).
     */
    public function trim(int $keep): self
    {
        if (count($this->messages) <= $keep) {
            return $this;
        }
        $slice = array_slice($this->messages, -$keep);
        while ($slice && $slice[0]['role'] !== 'user') {
            array_shift($slice);
        }
        $this->messages = array_values($slice);
        return $this;
    }

    public function toArray(): array
    {
        return ['system' => $this->system, 'messages' => $this->messages];
    }

    public static function fromArray(array $data): self
    {
        $c = new self();
        $c->system = (string) ($data['system'] ?? '');
        $c->messages = array_values((array) ($data['messages'] ?? []));
        return $c;
    }
}
