<?php

/**
 * Masks personal data in text before it is sent to a model provider.
 * @license MIT
 */
class AiNative_Core_Helper_Redact extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'AiNative_Core';

    private const EMAIL = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';
    // phone-like: leading + or ( , OR digit groups joined by spaces/dashes/dots (e.g. 555-123-4567, 020 7946 0958).
    // Bare digit runs (order numbers, SKUs, zip codes) are deliberately NOT matched.
    private const PHONE = '/(?<![\w.])(?:\+\d[\d\s().-]{7,}\d|\(\d{2,4}\)[\s.-]?\d[\d\s.-]{5,}\d|\d{2,4}(?:[\s.-]\d{2,4}){2,4})(?!\w|\.\d)/';

    public function text(string $text): string
    {
        $text = (string) preg_replace_callback(self::EMAIL, fn(array $m) => $this->maskEmail($m[0]), $text);
        return (string) preg_replace_callback(self::PHONE, fn(array $m) => $this->maskPhone($m[0]), $text);
    }

    /**
     * Redact recursively in an array structure (tool results).
     */
    public function data(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->text($value);
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->data($v);
            }
        }
        return $value;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_substr($local, 0, 1);
        return $keep . '***@' . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 9) {
            return $phone;
        }
        return str_repeat('*', max(0, strlen($digits) - 3)) . substr($digits, -3);
    }
}
