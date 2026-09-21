<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Provider failures must carry their HTTP status and whether a retry makes sense, so the
 * storefront can show a neutral apology while the admin sees the detail.
 */
final class ProviderErrorTest extends TestCase
{
    public function testOverloadIsTransientAndRetryable(): void
    {
        $e = new AiNative_Core_Exception_Provider('Anthropic API error — HTTP 529: overloaded', 529, true);
        self::assertSame(529, $e->getStatus());
        self::assertTrue($e->isRetryable());
        self::assertTrue($e->isTransient());
    }

    public function testRateLimitAndServerErrorsAreTransient(): void
    {
        foreach ([429, 500, 502, 503, 504, 529] as $status) {
            self::assertTrue((new AiNative_Core_Exception_Provider('x', $status))->isTransient(), "status {$status}");
        }
    }

    public function testBadKeyIsNotTransient(): void
    {
        $e = new AiNative_Core_Exception_Provider('Anthropic API error — HTTP 401: invalid x-api-key', 401, false);
        self::assertFalse($e->isTransient(), 'a bad key must not be presented as a temporary glitch');
    }

    public function testTransportFailureWithNoStatusIsRetryable(): void
    {
        self::assertTrue((new AiNative_Core_Exception_Provider('transport error', 0, true))->isTransient());
    }

    public function testItIsStillACoreExceptionSoExistingHandlersCatchIt(): void
    {
        self::assertInstanceOf(AiNative_Core_Exception::class, new AiNative_Core_Exception_Provider('x', 500, true));
    }
}
