<?php

class AiNative_Copilot_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'AiNative_Copilot';

    public function isEnabled(): bool
    {
        return Mage::helper('ainative_core')->isEnabled() && Mage::getStoreConfigFlag('ainative_copilot/general/enabled');
    }

    public function getTone(?int $storeId = null): string
    {
        return (string) Mage::getStoreConfig('ainative_copilot/general/tone', $storeId) ?: 'professional';
    }

    public function getLanguage(?int $storeId = null): string
    {
        $lang = trim((string) Mage::getStoreConfig('ainative_copilot/general/language', $storeId));
        if ($lang !== '') {
            return $lang;
        }
        $locale = (string) Mage::getStoreConfig('general/locale/code', $storeId);
        return $locale ? Zend_Locale::getTranslation(substr($locale, 0, 2), 'language', 'en') ?: $locale : 'English';
    }

    public function getBrandGuidelines(?int $storeId = null): string
    {
        return trim((string) Mage::getStoreConfig('ainative_copilot/general/brand_guidelines', $storeId));
    }

    public function getJobsPerRun(): int
    {
        return max(1, (int) Mage::getStoreConfig('ainative_copilot/general/jobs_per_run'));
    }

    public function getAskHistory(): int
    {
        return max(4, (int) Mage::getStoreConfig('ainative_copilot/general/ask_history'));
    }

    public function isAllowed(string $resource): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed($resource);
    }
}
