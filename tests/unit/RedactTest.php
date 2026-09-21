<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RedactTest extends TestCase
{
    private AiNative_Core_Helper_Redact $redact;

    protected function setUp(): void
    {
        $this->redact = new AiNative_Core_Helper_Redact();
    }

    public function testMasksEmailsAndPhones(): void
    {
        $out = $this->redact->text('Mail jane.doe@example.com or call +1 (555) 123-4567 / 020 7946 0958.');
        self::assertStringNotContainsString('jane.doe@', $out);
        self::assertStringContainsString('j***@example.com', $out);
        self::assertStringNotContainsString('123-4567', $out);
        self::assertStringNotContainsString('7946 0958', $out);
    }

    public function testLeavesOrderNumbersSkusAndPricesAlone(): void
    {
        $text = 'Order 145000004 for SKU msj006c-Royal-L totals 975.55, qty 100, zip 90210, from 2013-04-01 to 2013-04-30, v1.2.3.4';
        self::assertSame($text, $this->redact->text($text));
    }

    public function testRedactsNestedArrays(): void
    {
        $out = $this->redact->data(['a' => ['email' => 'x@y.com'], 'b' => 'plain']);
        self::assertSame('x***@y.com', $out['a']['email']);
        self::assertSame('plain', $out['b']);
    }
}
