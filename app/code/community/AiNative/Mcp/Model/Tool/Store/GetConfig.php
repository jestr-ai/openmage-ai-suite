<?php

class AiNative_Mcp_Model_Tool_Store_GetConfig extends AiNative_Core_Model_Tool_Abstract
{
    /** Only these top-level sections can be read. Secrets are additionally filtered by key name. */
    public const ALLOWED_SECTIONS = ['general', 'catalog', 'cataloginventory', 'sales', 'sales_email', 'shipping', 'carriers', 'tax', 'currency', 'checkout', 'customer', 'design', 'web', 'cms', 'contacts', 'newsletter', 'promo', 'sitemap', 'wishlist', 'sendfriend', 'ainative', 'ainative_mcp'];
    public const DENY_KEY_PATTERN = '/(password|passwd|secret|api_key|apikey|private|token|signature|account|login|user(name)?$|merchant_id|encryption|salt|hash|credential|gateway_url|trans_key|pwd)/i';

    public function getName(): string
    {
        return 'get_config';
    }

    public function getDescription(): string
    {
        return 'Read store configuration values by path prefix (e.g. "shipping/origin", "catalog/frontend", "general/store_information", "carriers/flatrate", "tax/calculation"). Only non-sensitive sections are readable (' . implode(', ', self::ALLOWED_SECTIONS) . '); credentials are always hidden. Pass store_id for store-view scope.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'path' => ['type' => 'string', 'description' => 'section/group or section/group/field'],
            'store_id' => ['type' => 'integer'],
        ], ['path']);
    }

    public function getAclResource(): ?string
    {
        return 'admin/system/config';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $path = trim($this->str($args, 'path'), '/');
        if (!preg_match('~^[a-z0-9_]+(/[a-z0-9_]+){0,2}$~i', $path)) {
            $this->fail('Path must look like section/group or section/group/field.');
        }
        $section = explode('/', $path)[0];
        if (!in_array($section, self::ALLOWED_SECTIONS, true)) {
            $this->fail(sprintf('Section "%s" is not readable via AI. Allowed: %s', $section, implode(', ', self::ALLOWED_SECTIONS)));
        }
        $storeId = isset($args['store_id']) ? $this->int($args, 'store_id', 0, 0) : null;
        $node = Mage::getStoreConfig($path, $storeId);
        $values = [];
        $this->flatten($path, $node, $values);
        return ['path' => $path, 'store_id' => $storeId, 'values' => $values];
    }

    private function flatten(string $prefix, mixed $node, array &$out): void
    {
        if (is_array($node)) {
            foreach ($node as $k => $v) {
                $this->flatten($prefix . '/' . $k, $v, $out);
            }
            return;
        }
        $leaf = substr($prefix, (int) strrpos($prefix, '/') + 1);
        if (preg_match(self::DENY_KEY_PATTERN, $leaf)) {
            $out[$prefix] = '[hidden]';
            return;
        }
        $out[$prefix] = $node === null ? null : (string) $node;
    }
}
