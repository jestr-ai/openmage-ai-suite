<?php

/**
 * Builds per-store product feeds in an ACP/Google-Merchant-compatible field set (JSON lines + CSV).
 * @license MIT
 */
class AiNative_Discovery_Model_Feed
{
    public const COLUMNS = ['id', 'item_group_id', 'title', 'description', 'link', 'image_link', 'additional_image_link', 'price', 'sale_price', 'sale_price_effective_date', 'currency', 'availability', 'inventory_quantity', 'brand', 'gtin', 'mpn', 'condition', 'product_type', 'google_product_category', 'weight', 'color', 'size', 'material', 'seller_name', 'return_policy_url', 'shipping', 'updated_at'];

    public function buildAll(): void
    {
        foreach (Mage::app()->getStores() as $store) {
            if (Mage::helper('ainative_discovery')->isEnabled((int) $store->getId())) {
                try {
                    $this->build($store);
                } catch (Throwable $e) {
                    Mage::helper('ainative_core')->log('feed build failed for ' . $store->getCode() . ': ' . $e->getMessage(), null, Zend_Log::ERR);
                }
            }
        }
    }

    /**
     * @return array{products:int, json:string, csv:string}
     */
    public function build(Mage_Core_Model_Store $store): array
    {
        $helper = Mage::helper('ainative_discovery');
        $emulation = Mage::getSingleton('core/app_emulation');
        $env = $emulation->startEnvironmentEmulation((int) $store->getId(), Mage_Core_Model_App_Area::AREA_FRONTEND);
        $jsonPath = $helper->getFeedPath($store, 'json');
        $csvPath = $helper->getFeedPath($store, 'csv');
        $jsonTmp = $jsonPath . '.tmp';
        $csvTmp = $csvPath . '.tmp';
        $jf = fopen($jsonTmp, 'w');
        $cf = fopen($csvTmp, 'w');
        if (!$jf || !$cf) {
            throw new AiNative_Core_Exception('Cannot write feed files in media/ainative/feed.');
        }
        fputcsv($cf, self::COLUMNS);
        $count = 0;
        try {
            $page = 1;
            do {
                $collection = $this->collection($store)->setPageSize(200)->setCurPage($page);
                $collection->load();
                foreach ($collection as $product) {
                    foreach ($this->rows($product, $store) as $row) {
                        fwrite($jf, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                        fputcsv($cf, array_map(fn($k) => is_array($row[$k] ?? null) ? implode(',', $row[$k]) : (string) ($row[$k] ?? ''), self::COLUMNS));
                        $count++;
                    }
                }
                $last = $collection->getLastPageNumber();
                $collection->clear();
                $page++;
            } while ($page <= $last);
        } finally {
            fclose($jf);
            fclose($cf);
            $emulation->stopEnvironmentEmulation($env);
        }
        rename($jsonTmp, $jsonPath);
        rename($csvTmp, $csvPath);
        Mage::helper('ainative_core')->log(sprintf('feed built for store %s: %d rows', $store->getCode(), $count));
        return ['products' => $count, 'json' => $jsonPath, 'csv' => $csvPath];
    }

    public function collection(Mage_Core_Model_Store $store): Mage_Catalog_Model_Resource_Product_Collection
    {
        $helper = Mage::helper('ainative_discovery');
        $attrs = array_filter(['name', 'description', 'short_description', 'price', 'special_price', 'special_from_date', 'special_to_date', 'image', 'small_image', 'url_key', 'url_path', 'weight', 'color', 'size', 'material', 'updated_at', 'tax_class_id', 'visibility', $helper->cfg('brand_attribute'), $helper->cfg('gtin_attribute'), $helper->cfg('mpn_attribute')]);
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId((int) $store->getId())->addStoreFilter($store)->addWebsiteFilter($store->getWebsiteId())
            ->addAttributeToSelect(array_values(array_unique($attrs)))
            ->addAttributeToFilter('type_id', ['in' => ['simple', 'configurable', 'virtual', 'downloadable', 'bundle', 'grouped']])
            ->addPriceData(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID, (int) $store->getWebsiteId())->addTaxPercents()->addUrlRewrite()
            ->joinField('qty', 'cataloginventory/stock_item', 'qty', 'product_id=entity_id', '{{table}}.stock_id=1', 'left')
            ->joinField('is_in_stock', 'cataloginventory/stock_item', 'is_in_stock', 'product_id=entity_id', '{{table}}.stock_id=1', 'left')
            ->setOrder('entity_id', 'ASC');
        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInSiteFilterToCollection($collection);
        if (!$helper->flag('include_out_of_stock', (int) $store->getId())) {
            $collection->addFieldToFilter('is_in_stock', 1);
        }
        return $collection;
    }

    /**
     * One row per sellable item: configurable → one row per child with item_group_id; others → one row.
     * @return array<int, array<string, mixed>>
     */
    public function rows(Mage_Catalog_Model_Product $product, Mage_Core_Model_Store $store): array
    {
        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $rows = [];
            $type = $product->getTypeInstance(true);
            $attrs = [];
            foreach ($type->getConfigurableAttributes($product) as $ca) {
                $attrs[] = $ca->getProductAttribute()->getAttributeCode();
            }
            foreach ($type->getUsedProducts(null, $product) as $child) {
                if (!$child->isSaleable() && !Mage::helper('ainative_discovery')->flag('include_out_of_stock', (int) $store->getId())) {
                    continue;
                }
                $row = $this->row($child, $store, $product);
                $variant = [];
                foreach ($attrs as $code) {
                    $label = $child->getAttributeText($code) ?: $child->getData($code);
                    $row[$code] = $label;
                    if ($label !== null && $label !== '') {
                        $variant[] = (string) $label;
                    }
                }
                $row['title'] = $product->getName() . ($variant ? ' - ' . implode(' / ', $variant) : '');
                $rows[] = $row;
            }
            return $rows ?: [$this->row($product, $store)];
        }
        return [$this->row($product, $store)];
    }

    private function row(Mage_Catalog_Model_Product $p, Mage_Core_Model_Store $store, ?Mage_Catalog_Model_Product $parent = null): array
    {
        $helper = Mage::helper('ainative_discovery');
        $storeId = (int) $store->getId();
        $display = $parent ?: $p;
        $currency = $store->getBaseCurrencyCode();
        // Grouped/bundle parents carry no own price; the price index holds the real minimum.
        $indexMin = $display->getData('min_price') !== null ? (float) $display->getData('min_price') : 0.0;
        $regular = (float) ($display->getPrice() ?: $indexMin);
        $final = (float) ($display->getFinalPrice() ?: ($indexMin ?: $regular));
        if ($parent) {
            $regular = (float) ($p->getPrice() ?: $parent->getPrice() ?: $indexMin);
            $final = (float) ($p->getFinalPrice() ?: $regular);
        }
        $images = [];
        $mediaConfig = $display->getMediaConfig();
        foreach ((array) ($display->getMediaGallery('images') ?: []) as $img) {
            if (empty($img['disabled']) && !empty($img['file'])) {
                $images[] = $mediaConfig->getMediaUrl($img['file']);
            }
        }
        $main = $display->getImage() && $display->getImage() !== 'no_selection' ? $mediaConfig->getMediaUrl($display->getImage()) : ($images[0] ?? null);
        $additional = array_values(array_filter($images, fn($u) => $u !== $main));
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($p);
        $categories = [];
        foreach ($display->getCategoryCollection()->addAttributeToSelect('name')->addFieldToFilter('level', ['gt' => 1]) as $c) {
            $categories[] = $c->getName();
        }
        $brandAttr = $helper->cfg('brand_attribute');
        $gtinAttr = $helper->cfg('gtin_attribute');
        $mpnAttr = $helper->cfg('mpn_attribute');
        $text = fn(?string $html) => trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)) ?? '');
        $row = [
            'id' => $p->getSku(),
            'item_group_id' => $parent ? $parent->getSku() : null,
            'title' => $parent ? $parent->getName() . ' - ' . $p->getName() : $p->getName(),
            'description' => mb_substr($text($display->getDescription() ?: $display->getShortDescription()), 0, 5000),
            'link' => $display->getProductUrl(),
            'image_link' => $main,
            'additional_image_link' => array_slice($additional, 0, 10),
            'price' => number_format($regular, 2, '.', '') . ' ' . $currency,
            'sale_price' => $final < $regular ? number_format($final, 2, '.', '') . ' ' . $currency : null,
            'sale_price_effective_date' => $final < $regular && $display->getSpecialFromDate() ? substr((string) $display->getSpecialFromDate(), 0, 10) . '/' . ($display->getSpecialToDate() ? substr((string) $display->getSpecialToDate(), 0, 10) : '') : null,
            'currency' => $currency,
            'availability' => $p->isSaleable() ? 'in_stock' : ((int) $stock->getBackorders() ? 'backorder' : 'out_of_stock'),
            'inventory_quantity' => (int) $stock->getManageStock() ? (int) $stock->getQty() : null,
            'brand' => $brandAttr ? ($display->getAttributeText($brandAttr) ?: $display->getData($brandAttr)) : null,
            'gtin' => $gtinAttr ? $p->getData($gtinAttr) : null,
            'mpn' => $mpnAttr ? $p->getData($mpnAttr) : null,
            'condition' => $helper->cfg('condition') ?: 'new',
            'product_type' => implode(' > ', $categories),
            'google_product_category' => null,
            'weight' => $p->getWeight() ? (float) $p->getWeight() . ' ' . (Mage::getStoreConfig('general/locale/weight_unit', $storeId) ?: 'lbs') : null,
            'color' => $p->getAttributeText('color') ?: null,
            'size' => $p->getAttributeText('size') ?: null,
            'material' => $p->getAttributeText('material') ?: null,
            'seller_name' => $helper->cfg('seller_name', $storeId) ?: (Mage::getStoreConfig('general/store_information/name', $storeId) ?: $store->getFrontendName()),
            'return_policy_url' => $helper->cfg('return_policy_url', $storeId) ?: null,
            'shipping' => $helper->cfg('shipping_note', $storeId) ?: null,
            'updated_at' => $p->getUpdatedAt(),
        ];
        if (is_array($row['brand'])) {
            $row['brand'] = implode(', ', $row['brand']);
        }
        return $row;
    }
}
