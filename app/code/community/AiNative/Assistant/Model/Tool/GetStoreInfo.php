<?php

class AiNative_Assistant_Model_Tool_GetStoreInfo extends AiNative_Assistant_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'get_store_info';
    }

    public function getDescription(): string
    {
        return 'Store policies and facts the merchant published: allowed information pages (shipping, returns, FAQ, about, privacy), contact details, currency, and shipping methods with their public titles. Call with a page identifier to read one page\'s text, or without arguments for the overview.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'page' => ['type' => 'string', 'description' => 'CMS page identifier from the overview list.'],
        ]);
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $store = $this->store($context);
        $helper = Mage::helper('ainative_assistant');
        $allowed = $helper->getAllowedCmsPages((int) $store->getId());
        $page = $this->str($args, 'page');
        if ($page !== '') {
            if (!in_array($page, $allowed, true)) {
                $this->fail('That page is not available to the assistant. Available: ' . implode(', ', $allowed));
            }
            $cms = Mage::getModel('cms/page')->setStoreId((int) $store->getId())->load($page, 'identifier');
            if (!$cms->getId() || !$cms->getIsActive()) {
                $this->fail('Page not found.');
            }
            $text = trim(preg_replace('/\s+/', ' ', strip_tags(Mage::helper('cms')->getPageTemplateProcessor()->filter((string) $cms->getContent()))) ?? '');
            return ['identifier' => $page, 'title' => $cms->getTitle(), 'url' => Mage::helper('cms/page')->getPageUrl($cms->getId()), 'text' => mb_substr($text, 0, 6000)];
        }
        $pages = [];
        if ($allowed) {
            foreach (Mage::getModel('cms/page')->getCollection()->addStoreFilter((int) $store->getId())->addFieldToFilter('identifier', ['in' => $allowed])->addFieldToFilter('is_active', 1) as $cms) {
                $pages[] = ['identifier' => $cms->getIdentifier(), 'title' => $cms->getTitle()];
            }
        }
        $carriers = [];
        foreach (Mage::getSingleton('shipping/config')->getActiveCarriers((int) $store->getId()) as $code => $carrier) {
            $carriers[] = ['code' => $code, 'title' => Mage::getStoreConfig("carriers/{$code}/title", $store)];
        }
        return [
            'store_name' => Mage::getStoreConfig('general/store_information/name', $store) ?: $store->getFrontendName(),
            'currency' => $store->getCurrentCurrencyCode(),
            'contact' => ['phone' => Mage::getStoreConfig('general/store_information/phone', $store), 'email' => Mage::getStoreConfig('trans_email/ident_support/email', $store), 'address' => Mage::getStoreConfig('general/store_information/address', $store), 'contact_url' => $helper->getContactUrl((int) $store->getId())],
            'shipping_methods' => $carriers,
            'free_shipping_threshold' => Mage::getStoreConfigFlag('carriers/freeshipping/active', $store) ? (float) Mage::getStoreConfig('carriers/freeshipping/free_shipping_subtotal', $store) : null,
            'information_pages' => $pages,
        ];
    }
}
