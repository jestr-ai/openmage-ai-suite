<?php

class AiNative_Mcp_Model_Tool_Store_DescribeStore extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'describe_store';
    }

    public function getDescription(): string
    {
        return 'Overview of the installation: OpenMage/Magento version, websites → stores → store views (ids, codes, names, base URLs, locale, currencies, root category), timezone, order statuses, installed community/local modules, and counts of products, categories, customers and orders. Call this first.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/system';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $websites = [];
        foreach (Mage::app()->getWebsites() as $website) {
            $stores = [];
            foreach ($website->getGroups() as $group) {
                $views = [];
                foreach ($group->getStores() as $store) {
                    $views[] = [
                        'store_id' => (int) $store->getId(),
                        'code' => $store->getCode(),
                        'name' => $store->getName(),
                        'is_active' => (bool) $store->getIsActive(),
                        'base_url' => $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB),
                        'locale' => Mage::getStoreConfig('general/locale/code', $store),
                        'base_currency' => $store->getBaseCurrencyCode(),
                        'default_currency' => $store->getDefaultCurrencyCode(),
                        'allowed_currencies' => explode(',', (string) Mage::getStoreConfig('currency/options/allow', $store)),
                        'timezone' => Mage::getStoreConfig('general/locale/timezone', $store),
                    ];
                }
                $stores[] = ['group_id' => (int) $group->getId(), 'name' => $group->getName(), 'root_category_id' => (int) $group->getRootCategoryId(), 'default_store_id' => (int) $group->getDefaultStoreId(), 'store_views' => $views];
            }
            $websites[] = ['website_id' => (int) $website->getId(), 'code' => $website->getCode(), 'name' => $website->getName(), 'is_default' => (bool) $website->getIsDefault(), 'stores' => $stores];
        }
        $modules = [];
        foreach (Mage::getConfig()->getNode('modules')->children() as $name => $node) {
            $pool = (string) $node->codePool;
            if ($pool === 'core' || (string) $node->active !== 'true') {
                continue;
            }
            $modules[] = ['name' => $name, 'code_pool' => $pool, 'version' => (string) $node->version];
        }
        $statuses = [];
        foreach (Mage::getModel('sales/order_status')->getResourceCollection()->joinStates() as $s) {
            $statuses[] = ['status' => $s->getStatus(), 'label' => $s->getLabel(), 'state' => $s->getState()];
        }
        $resource = Mage::getSingleton('core/resource');
        $conn = $resource->getConnection('core_read');
        $count = fn(string $table) => (int) $conn->fetchOne('SELECT COUNT(*) FROM ' . $resource->getTableName($table));
        return [
            'platform' => 'OpenMage LTS',
            'openmage_version' => Mage::getOpenMageVersion(),
            'magento_version' => Mage::getVersion(),
            'php_version' => PHP_VERSION,
            'store_name' => Mage::getStoreConfig('general/store_information/name'),
            'default_timezone' => Mage::getStoreConfig('general/locale/timezone'),
            'now_store_time' => Mage::getSingleton('core/date')->date('Y-m-d H:i:s'),
            'websites' => $websites,
            'order_statuses' => $statuses,
            'counts' => [
                'products' => $count('catalog/product'),
                'categories' => $count('catalog/category'),
                'customers' => $count('customer/entity'),
                'orders' => $count('sales/order'),
            ],
            'modules' => $modules,
            'ai_suite' => ['write_tools_allowed' => Mage::helper('ainative_core')->isWriteAllowed(), 'your_scope' => $context->canWrite() ? 'read/write' : 'read'],
        ];
    }
}
