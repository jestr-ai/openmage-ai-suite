<?php

/**
 * Convenience base: pagination, store resolution, arg accessors.
 * @license MIT
 */
abstract class AiNative_Core_Model_Tool_Abstract implements AiNative_Core_Model_Tool_Interface
{
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;

    public function getAclResource(): ?string
    {
        return null;
    }

    public function isWrite(): bool
    {
        return false;
    }

    protected function str(array $args, string $key, string $default = ''): string
    {
        $v = $args[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    protected function int(array $args, string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $v = isset($args[$key]) && is_numeric($args[$key]) ? (int) $args[$key] : $default;
        if ($min !== null) {
            $v = max($min, $v);
        }
        if ($max !== null) {
            $v = min($max, $v);
        }
        return $v;
    }

    protected function bool(array $args, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $args)) {
            return $default;
        }
        return filter_var($args[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    protected function pageSize(array $args, string $key = 'limit'): int
    {
        return $this->int($args, $key, self::DEFAULT_PAGE_SIZE, 1, self::MAX_PAGE_SIZE);
    }

    protected function page(array $args, string $key = 'page'): int
    {
        return $this->int($args, $key, 1, 1);
    }

    /**
     * @return array{items: array, page: int, page_size: int, total: int, has_more: bool}
     */
    protected function paginate(Varien_Data_Collection $collection, int $page, int $size, callable $map): array
    {
        $collection->setPageSize($size)->setCurPage($page);
        $total = $collection->getSize();
        $items = [];
        foreach ($collection as $item) {
            $items[] = $map($item);
        }
        return [
            'items' => $items,
            'page' => $page,
            'page_size' => $size,
            'total' => (int) $total,
            'has_more' => $page * $size < $total,
        ];
    }

    protected function store(AiNative_Core_Model_Tool_Context $context): Mage_Core_Model_Store
    {
        return Mage::app()->getStore($context->getStoreId());
    }

    protected function helper(): AiNative_Core_Helper_Data
    {
        return Mage::helper('ainative_core');
    }

    protected function schema(array $properties, array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
        if ($required) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    protected function fail(string $message): never
    {
        throw new AiNative_Core_Exception($message);
    }

    /** Store timezone from config (Mage::app()->getLocale() can return the request store's, not the target's). */
    protected function storeTimezone(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $storeId) ?: 'UTC';
    }

    protected function formatDate(?string $date): ?string
    {
        return $date ? Mage::getSingleton('core/date')->date('Y-m-d H:i:s', $date) : null;
    }

    /**
     * Format money for a store. Mage_Core_Model_Store::formatPrice() resolves the *current* currency via the
     * session, which is unavailable in cron and CLI; fall back to the store's base currency there.
     */
    protected function price(float|string|null $amount, Mage_Core_Model_Store $store): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        try {
            return $store->formatPrice((float) $amount, false);
        } catch (Throwable) {
            return $store->getBaseCurrency()->formatPrecision((float) $amount, 2, [], false);
        }
    }
}
