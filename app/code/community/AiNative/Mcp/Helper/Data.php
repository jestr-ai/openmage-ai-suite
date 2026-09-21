<?php

class AiNative_Mcp_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'AiNative_Mcp';

    public function isEnabled(): bool
    {
        return Mage::helper('ainative_core')->isEnabled() && Mage::getStoreConfigFlag('ainative_mcp/general/enabled');
    }

    public function getServerName(): string
    {
        return (string) Mage::getStoreConfig('ainative_mcp/general/server_name') ?: 'OpenMage Store';
    }

    /** @return string[] */
    public function getAllowedOrigins(): array
    {
        $raw = (string) Mage::getStoreConfig('ainative_mcp/general/allowed_origins');
        $list = array_filter(array_map(fn($l) => rtrim(trim($l), '/'), preg_split('/[\r\n,]+/', $raw) ?: []));
        foreach ([Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, true)] as $base) {
            $parts = parse_url($base);
            if ($parts && isset($parts['host'])) {
                $list[] = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            }
        }
        return array_values(array_unique($list));
    }

    public function isUrlTokenAllowed(): bool
    {
        return Mage::getStoreConfigFlag('ainative_mcp/general/allow_url_token');
    }

    public function isSqlEnabled(): bool
    {
        return Mage::getStoreConfigFlag('ainative_mcp/sql/enabled');
    }

    public function getSqlMaxRows(): int
    {
        return max(1, min(500, (int) Mage::getStoreConfig('ainative_mcp/sql/max_rows')));
    }

    /** @return string[] */
    public function getSqlDeniedTables(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) Mage::getStoreConfig('ainative_mcp/sql/denied_tables')))));
    }

    public function getEndpointUrl(): string
    {
        return Mage::getUrl('ainative-mcp', ['_secure' => true, '_nosid' => true]);
    }
}
