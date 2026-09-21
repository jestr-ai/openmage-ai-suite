<?php

/**
 * Result of one provider completion.
 * @license MIT
 */
final class AiNative_Core_Model_Turn
{
    /**
     * @param AiNative_Core_Model_ToolCall[] $toolCalls
     */
    public function __construct(
        private readonly ?string $text,
        private readonly array $toolCalls,
        private readonly int $tokensIn,
        private readonly int $tokensOut,
        private readonly string $stopReason,
        private readonly string $model,
        private readonly array $raw = [],
    ) {}

    public function getText(): ?string
    {
        return $this->text;
    }

    /** @return AiNative_Core_Model_ToolCall[] */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    public function getToolCallsAsArray(): array
    {
        return array_map(fn(AiNative_Core_Model_ToolCall $c) => $c->toArray(), $this->toolCalls);
    }

    public function getTokensIn(): int
    {
        return $this->tokensIn;
    }

    public function getTokensOut(): int
    {
        return $this->tokensOut;
    }

    public function getStopReason(): string
    {
        return $this->stopReason;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }

    public function isRefusal(): bool
    {
        return $this->stopReason === 'refusal';
    }

    public function isTruncated(): bool
    {
        return in_array($this->stopReason, ['max_tokens', 'length', 'MAX_TOKENS'], true);
    }
}
