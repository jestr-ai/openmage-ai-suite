<?php

/**
 * /ainative-discovery/index/llms   → llms.txt (also served at /llms.txt)
 * /ainative-discovery/index/catalog → paginated LLM-friendly catalog JSON
 * /ainative-discovery/index/categories → category tree JSON
 * @license MIT
 */
class AiNative_Discovery_IndexController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, true);
        AiNative_Core_Model_Visitor_Noop::install();
        Mage::app()->setUseSessionInUrl(false);
        return parent::preDispatch();
    }

    public function llmsAction(): void
    {
        $helper = Mage::helper('ainative_discovery');
        if (!$helper->isEnabled() || !$helper->flag('llms_txt')) {
            $this->norouteAction();
            return;
        }
        $store = Mage::app()->getStore();
        $storeId = (int) $store->getId();
        $base = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $name = Mage::getStoreConfig('general/store_information/name', $storeId) ?: $store->getFrontendName();
        $lines = ['# ' . $name, ''];
        $ctx = Mage::helper('ainative_core')->getStoreContext($storeId);
        $lines[] = '> ' . ($ctx !== '' ? $ctx : 'Online store running OpenMage. Product data below is machine-readable and refreshed regularly.');
        $lines[] = '';
        $lines[] = 'Currency: ' . $store->getCurrentCurrencyCode() . '. Locale: ' . Mage::getStoreConfig('general/locale/code', $storeId) . '.';
        if ($helper->cfg('shipping_note', $storeId) !== '') {
            $lines[] = 'Shipping: ' . $helper->cfg('shipping_note', $storeId);
        }
        if ($helper->cfg('return_policy_url', $storeId) !== '') {
            $lines[] = 'Returns: ' . $helper->cfg('return_policy_url', $storeId);
        }
        $lines[] = '';
        $lines[] = '## Machine-readable catalog';
        $lines[] = '';
        $lines[] = '- [Product feed (JSON lines)](' . $base . 'ainative-discovery/feed/products/store/' . $store->getCode() . '.json): one sellable item per line; fields follow the Google Merchant / Agentic Commerce Protocol product feed vocabulary (id, title, description, link, image_link, price, sale_price, availability, brand, gtin, mpn, item_group_id…).';
        $lines[] = '- [Product feed (CSV)](' . $base . 'ainative-discovery/feed/products/store/' . $store->getCode() . '.csv)';
        if ($helper->flag('catalog_api', $storeId)) {
            $lines[] = '- [Catalog API](' . $base . 'ainative-discovery/index/catalog): paginated JSON of products with shopper prices and stock (`?page=N&category_id=X&q=keywords`).';
            $lines[] = '- [Categories API](' . $base . 'ainative-discovery/index/categories): category tree with ids for the catalog API.';
        }
        if (Mage::getStoreConfigFlag('sitemap/generate/enabled', $storeId) || is_file(Mage::getBaseDir() . '/sitemap.xml')) {
            $lines[] = '- [Sitemap](' . $base . 'sitemap.xml)';
        }
        $lines[] = '';
        $lines[] = '## Categories';
        $lines[] = '';
        $root = (int) $store->getRootCategoryId();
        $cats = Mage::getModel('catalog/category')->getCollection()->setStoreId($storeId)->addAttributeToSelect(['name', 'url_key', 'url_path'])
            ->addIsActiveFilter()->addAttributeToFilter('include_in_menu', 1)->addFieldToFilter('path', ['like' => '1/' . $root . '/%'])->addFieldToFilter('level', ['lteq' => 3])->addOrderField('path');
        foreach ($cats as $c) {
            $lines[] = str_repeat('  ', max(0, (int) $c->getLevel() - 2)) . '- [' . $c->getName() . '](' . $c->getUrl() . ')';
        }
        $lines[] = '';
        $lines[] = '## Information pages';
        $lines[] = '';
        $pages = Mage::getModel('cms/page')->getCollection()->addStoreFilter($storeId)->addFieldToFilter('is_active', 1)->addFieldToFilter('identifier', ['nin' => ['no-route', 'home', 'enable-cookies']]);
        foreach ($pages as $p) {
            $lines[] = '- [' . $p->getTitle() . '](' . Mage::helper('cms/page')->getPageUrl($p->getId()) . ')';
        }
        if ((int) $this->getRequest()->getParam('full') === 1) {
            $lines[] = '';
            $lines[] = '## Products (sample of up to 200, newest first)';
            $lines[] = '';
            $feed = Mage::getModel('ainative_discovery/feed');
            $collection = $feed->collection($store)->setOrder('created_at', 'DESC')->setPageSize(200)->setCurPage(1);
            foreach ($collection as $product) {
                $final = (float) ($product->getFinalPrice() ?: $product->getPrice());
                $lines[] = sprintf('- [%s](%s) — %s%s — %s', $product->getName(), $product->getProductUrl(), $store->formatPrice($final, false), $product->getSpecialPrice() && $final < (float) $product->getPrice() ? ' (sale)' : '', $product->isSaleable() ? 'in stock' : 'out of stock');
            }
        }
        $this->getResponse()->setHeader('Content-Type', 'text/plain; charset=utf-8', true)->setHeader('Cache-Control', 'public, max-age=3600', true)->setBody(implode("\n", $lines) . "\n");
    }

    public function catalogAction(): void
    {
        $helper = Mage::helper('ainative_discovery');
        if (!$helper->isEnabled() || !$helper->flag('catalog_api')) {
            $this->norouteAction();
            return;
        }
        $store = Mage::app()->getStore();
        $request = $this->getRequest();
        $page = max(1, (int) $request->getParam('page', 1));
        $size = min(100, max(1, (int) $request->getParam('limit', 50)));
        $feed = Mage::getModel('ainative_discovery/feed');
        $collection = $feed->collection($store);
        if (($cat = (int) $request->getParam('category_id')) > 0) {
            $collection->addCategoryFilter(Mage::getModel('catalog/category')->load($cat));
        }
        if (($q = trim((string) $request->getParam('q', ''))) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_substr($q, 0, 100)) . '%';
            $collection->addAttributeToFilter([['attribute' => 'name', 'like' => $like], ['attribute' => 'sku', 'like' => $like], ['attribute' => 'description', 'like' => $like]]);
        }
        $collection->setPageSize($size)->setCurPage($page);
        $items = [];
        foreach ($collection as $product) {
            foreach ($feed->rows($product, $store) as $row) {
                unset($row['seller_name'], $row['return_policy_url'], $row['shipping']);
                $items[] = $row;
            }
        }
        $total = (int) $collection->getSize();
        $this->json([
            'store' => $store->getCode(),
            'currency' => $store->getBaseCurrencyCode(),
            'page' => $page,
            'page_size' => $size,
            'total_products' => $total,
            'has_more' => $page * $size < $total,
            'next' => $page * $size < $total ? Mage::getUrl('ainative-discovery/index/catalog', ['_query' => array_filter(['page' => $page + 1, 'limit' => $size, 'category_id' => $cat ?: null, 'q' => $q ?: null])]) : null,
            'items' => $items,
        ]);
    }

    public function categoriesAction(): void
    {
        $helper = Mage::helper('ainative_discovery');
        if (!$helper->isEnabled() || !$helper->flag('catalog_api')) {
            $this->norouteAction();
            return;
        }
        $store = Mage::app()->getStore();
        $root = (int) $store->getRootCategoryId();
        $cats = Mage::getModel('catalog/category')->getCollection()->setStoreId((int) $store->getId())->addAttributeToSelect(['name', 'url_key', 'url_path', 'description'])
            ->addIsActiveFilter()->addFieldToFilter('path', ['like' => '1/' . $root . '/%'])->setProductStoreId((int) $store->getId())->setLoadProductCount(true)->addOrderField('path');
        $items = [];
        foreach ($cats as $c) {
            $items[] = ['id' => (int) $c->getId(), 'parent_id' => (int) $c->getParentId(), 'level' => (int) $c->getLevel(), 'name' => $c->getName(), 'url' => $c->getUrl(), 'product_count' => (int) $c->getProductCount(), 'description' => mb_substr(trim(strip_tags((string) $c->getDescription())), 0, 400) ?: null, 'catalog_api' => Mage::getUrl('ainative-discovery/index/catalog', ['_query' => ['category_id' => (int) $c->getId()]])];
        }
        $this->json(['store' => $store->getCode(), 'categories' => $items]);
    }

    private function json(array $data): void
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json; charset=utf-8', true)->setHeader('Cache-Control', 'public, max-age=900', true)
            ->setHeader('Access-Control-Allow-Origin', '*', true)
            ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}');
    }
}
