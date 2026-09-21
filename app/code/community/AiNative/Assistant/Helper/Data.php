<?php

class AiNative_Assistant_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'AiNative_Assistant';

    public function isEnabled(?int $storeId = null): bool
    {
        return Mage::helper('ainative_core')->isEnabled($storeId) && Mage::getStoreConfigFlag('ainative_assistant/general/enabled', $storeId);
    }

    public function cfg(string $key, ?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig('ainative_assistant/general/' . $key, $storeId);
    }

    public function flag(string $key, ?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag('ainative_assistant/general/' . $key, $storeId);
    }

    /** @return string[] */
    public function getSuggestions(?int $storeId = null): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $this->cfg('suggestions', $storeId)))));
    }

    /** @return string[] */
    public function getAllowedCmsPages(?int $storeId = null): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->cfg('cms_pages', $storeId)))));
    }

    public function getContactUrl(?int $storeId = null): string
    {
        $path = trim($this->cfg('contact_url', $storeId)) ?: 'contacts';
        return preg_match('~^https?://~', $path) ? $path : Mage::getUrl($path, ['_store' => $storeId]);
    }

    public function getMaxMessageLength(): int
    {
        return max(50, (int) $this->cfg('max_message_length'));
    }

    public function getHistoryMessages(): int
    {
        return max(4, (int) $this->cfg('history_messages'));
    }

    public function getRateLimit(): int
    {
        return (int) $this->cfg('rate_limit_per_minute');
    }

    public function getRetentionDays(): int
    {
        return (int) $this->cfg('retention_days');
    }

    public function getChatUrl(): string
    {
        return Mage::getUrl('ainative-assistant/chat/send', ['_secure' => Mage::app()->getStore()->isCurrentlySecure()]);
    }
}
