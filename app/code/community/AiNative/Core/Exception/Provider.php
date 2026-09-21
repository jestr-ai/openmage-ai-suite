<?php

/**
 * A failure talking to the model provider (network, rate limit, overload, bad key).
 * Distinct from AiNative_Core_Exception so callers can decide what the end user sees:
 * merchants get the detail, shoppers get a neutral apology.
 *
 * @license MIT
 */
class AiNative_Core_Exception_Provider extends AiNative_Core_Exception
{
    public function __construct(string $message, private readonly int $status = 0, private readonly bool $retryable = false)
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /** True when the provider is momentarily unavailable rather than misconfigured. */
    public function isTransient(): bool
    {
        return $this->retryable || in_array($this->status, [429, 500, 502, 503, 504, 529], true);
    }
}
