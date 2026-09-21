<?php

/**
 * Fixed-window per-minute limiter backed by a small table (works without a shared cache).
 * @license MIT
 */
class AiNative_Core_Model_RateLimit
{
    /**
     * @throws AiNative_Core_Exception
     */
    public function hit(string $key, ?int $limit = null): void
    {
        $limit ??= Mage::helper('ainative_core')->getRateLimitPerMinute();
        if ($limit <= 0) {
            return;
        }
        $resource = Mage::getResourceSingleton('ainative_core/rate_limit');
        $bucket = substr(hash('sha256', $key), 0, 40) . ':' . date('YmdHi');
        $hits = $resource->increment($bucket);
        if ($hits > $limit) {
            throw new AiNative_Core_Exception('Too many requests. Please wait a minute and try again.');
        }
    }
}
