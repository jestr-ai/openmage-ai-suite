<?php

/**
 * OpenMage AI Suite — Core helper: configuration access, provider factory, logging.
 * @license MIT
 */
class AiNative_Core_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const LOG_FILE = 'ainative.log';

    public const XML_ENABLED = 'ainative/general/enabled';
    public const XML_PROVIDER = 'ainative/general/provider';
    public const XML_MAX_TOKENS = 'ainative/general/max_tokens';
    public const XML_TEMPERATURE = 'ainative/general/temperature';
    public const XML_TIMEOUT = 'ainative/general/timeout';
    public const XML_MAX_ITERATIONS = 'ainative/general/max_iterations';
    public const XML_STORE_CONTEXT = 'ainative/general/store_context';
    public const XML_ALLOW_WRITE = 'ainative/security/allow_write_tools';
    public const XML_REDACT_PII = 'ainative/security/redact_pii';
    public const XML_TOKEN_BUDGET = 'ainative/security/monthly_token_budget';
    public const XML_RATE_LIMIT = 'ainative/security/rate_limit_per_minute';
    public const XML_AUDIT_RETENTION = 'ainative/security/audit_retention_days';
    public const XML_DEBUG = 'ainative/security/debug_log';

    protected $_moduleName = 'AiNative_Core';

    public function isEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_ENABLED, $storeId);
    }

    public function getProviderCode(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig(self::XML_PROVIDER, $storeId) ?: 'anthropic';
    }

    /**
     * Build the configured provider (or a specific one).
     */
    public function getProvider(?string $code = null, ?int $storeId = null): AiNative_Core_Model_Provider_Interface
    {
        $code = $code ?: $this->getProviderCode($storeId);
        $provider = Mage::getModel('ainative_core/provider_' . $code);
        if (!$provider instanceof AiNative_Core_Model_Provider_Interface) {
            throw new AiNative_Core_Exception(sprintf('Unknown AI provider "%s".', $code));
        }
        $provider->setStoreId($storeId);
        return $provider;
    }

    public function getProviderConfig(string $code, string $key, ?int $storeId = null): ?string
    {
        $value = Mage::getStoreConfig('ainative/' . $code . '/' . $key, $storeId);
        return $value === null ? null : (string) $value;
    }

    public function getApiKey(string $code, ?int $storeId = null): string
    {
        $raw = (string) Mage::getStoreConfig('ainative/' . $code . '/api_key', $storeId);
        if ($raw === '') {
            return '';
        }
        // OpenMage decrypts values whose config node carries backend_model="…encrypted" when the config
        // loads, so getStoreConfig normally already returns the plain key. Older stores (or values written
        // directly to core_config_data) still return the ciphertext, so decrypt only when needed.
        if ($this->looksPlain($raw)) {
            return $raw;
        }
        $decrypted = trim((string) Mage::helper('core')->decrypt($raw));
        return $this->looksPlain($decrypted) ? $decrypted : $raw;
    }

    /** Printable ASCII with no control characters — what every provider API key looks like. */
    private function looksPlain(string $value): bool
    {
        return $value !== '' && preg_match('/^[\x21-\x7E]+$/', $value) === 1 && !preg_match('#^[A-Za-z0-9+/]{40,}={0,2}$#', $value);
    }

    public function getMaxTokens(?int $storeId = null): int
    {
        return max(64, (int) Mage::getStoreConfig(self::XML_MAX_TOKENS, $storeId));
    }

    public function getTemperature(?int $storeId = null): float
    {
        return (float) Mage::getStoreConfig(self::XML_TEMPERATURE, $storeId);
    }

    public function getTimeout(?int $storeId = null): int
    {
        return max(5, (int) Mage::getStoreConfig(self::XML_TIMEOUT, $storeId));
    }

    public function getMaxIterations(?int $storeId = null): int
    {
        return max(1, (int) Mage::getStoreConfig(self::XML_MAX_ITERATIONS, $storeId));
    }

    public function getStoreContext(?int $storeId = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_STORE_CONTEXT, $storeId));
    }

    public function isWriteAllowed(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_ALLOW_WRITE);
    }

    public function isRedactPii(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_REDACT_PII);
    }

    public function getMonthlyTokenBudget(): int
    {
        return (int) Mage::getStoreConfig(self::XML_TOKEN_BUDGET);
    }

    public function getRateLimitPerMinute(): int
    {
        return (int) Mage::getStoreConfig(self::XML_RATE_LIMIT);
    }

    public function getAuditRetentionDays(): int
    {
        return (int) Mage::getStoreConfig(self::XML_AUDIT_RETENTION);
    }

    public function isDebug(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_DEBUG);
    }

    /**
     * @param mixed $context
     */
    public function log(string $message, $context = null, int $level = Zend_Log::INFO): void
    {
        if ($context !== null) {
            $message .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        Mage::log($message, $level, self::LOG_FILE, true);
    }

    public function debug(string $message, mixed $context = null): void
    {
        if ($this->isDebug()) {
            $this->log($message, $context, Zend_Log::DEBUG);
        }
    }

    public function newRequestId(): string
    {
        return Mage::helper('core')->uniqHash('ai');
    }

    /**
     * Truncate any value to a bounded UTF-8 string for storage/logging.
     */
    public function summarize(mixed $value, int $limit = 4000): string
    {
        $text = is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit) . '…';
        }
        return $text;
    }
}
