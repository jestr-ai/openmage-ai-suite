<?php

class AiNative_Discovery_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'AiNative_Discovery';

    public function isEnabled(?int $storeId = null): bool
    {
        return Mage::helper('ainative_core')->isEnabled($storeId) && Mage::getStoreConfigFlag('ainative_discovery/general/enabled', $storeId);
    }

    public function cfg(string $key, ?int $storeId = null): string
    {
        return trim((string) Mage::getStoreConfig('ainative_discovery/general/' . $key, $storeId));
    }

    public function flag(string $key, ?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('ainative_discovery/general/' . $key, $storeId);
    }

    public function getFeedDir(): string
    {
        $dir = Mage::getBaseDir('media') . DS . 'ainative' . DS . 'feed';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            @file_put_contents($dir . DS . '.htaccess', "Order deny,allow\nDeny from all\n"); // served via controller only (token check)
        }
        return $dir;
    }

    public function getFeedPath(Mage_Core_Model_Store $store, string $format): string
    {
        return $this->getFeedDir() . DS . preg_replace('/[^a-z0-9_-]/i', '_', $store->getCode()) . '.' . ($format === 'csv' ? 'csv' : 'json');
    }

    public function checkFeedToken(Mage_Core_Controller_Request_Http $request): bool
    {
        $required = $this->cfg('feed_token');
        if ($required === '') {
            return true;
        }
        $given = (string) ($request->getParam('token') ?: $request->getHeader('X-Feed-Token'));
        return $given !== '' && hash_equals($required, $given);
    }
}
