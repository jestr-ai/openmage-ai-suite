<?php

class AiNative_Mcp_Model_Tool_Catalog_GetProduct extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'get_product';
    }

    public function getDescription(): string
    {
        return 'Fetch one product by id or SKU with all attributes: descriptions, meta fields, prices (regular, special, tier), stock, categories (with names), websites, images, custom options, and for configurable/grouped/bundle products the child SKUs.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'id' => ['type' => 'integer', 'description' => 'Product entity id'],
            'sku' => ['type' => 'string', 'description' => 'Exact SKU (used when id is not given)'],
            'store_id' => ['type' => 'integer', 'description' => 'Store view scope for values. Default 0 (admin/default).'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/catalog/products';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $storeId = $this->int($args, 'store_id', 0, 0);
        $product = Mage::getModel('catalog/product')->setStoreId($storeId);
        $id = $this->int($args, 'id');
        if ($id > 0) {
            $product->load($id);
        } elseif (($sku = $this->str($args, 'sku')) !== '') {
            $found = Mage::getModel('catalog/product')->getIdBySku($sku);
            if ($found) {
                $product->load((int) $found);
            }
        } else {
            $this->fail('Provide id or sku.');
        }
        if (!$product->getId()) {
            $this->fail('Product not found.');
        }
        return $this->export($product, $storeId);
    }

    public function export(Mage_Catalog_Model_Product $product, int $storeId): array
    {
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        $categories = [];
        foreach ($product->getCategoryCollection()->addAttributeToSelect('name') as $cat) {
            $categories[] = ['id' => (int) $cat->getId(), 'name' => $cat->getName(), 'path' => $cat->getPath()];
        }
        $attributes = [];
        foreach ($product->getAttributes() as $attribute) {
            $code = $attribute->getAttributeCode();
            if (in_array($code, ['media_gallery', 'gallery', 'tier_price', 'group_price', 'category_ids', 'image', 'small_image', 'thumbnail', 'options_container', 'required_options', 'has_options'], true)) {
                continue;
            }
            $value = $product->getData($code);
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            if ($attribute->usesSource() && $attribute->getFrontendInput() !== 'boolean') {
                try {
                    $label = $attribute->getFrontend()->getValue($product);
                    if (is_string($label) && $label !== '' && $label !== $value) {
                        $attributes[$code] = ['value' => $value, 'label' => $label];
                        continue;
                    }
                } catch (Throwable) {
                }
            }
            $attributes[$code] = is_numeric($value) && !str_contains((string) $value, '.') && strlen((string) $value) < 10 ? (int) $value : $value;
        }
        $images = [];
        $mediaConfig = $product->getMediaConfig();
        $gallery = $product->getMediaGallery('images');
        foreach (is_array($gallery) ? $gallery : [] as $img) {
            if (!empty($img['disabled']) || empty($img['file'])) {
                continue;
            }
            $images[] = ['url' => $mediaConfig->getMediaUrl((string) $img['file']), 'label' => $img['label'] ?? null, 'position' => (int) ($img['position'] ?? 0)];
        }
        if (!$images && $product->getImage() && $product->getImage() !== 'no_selection') {
            $images[] = ['url' => $mediaConfig->getMediaUrl($product->getImage()), 'label' => null, 'position' => 1];
        }
        $children = [];
        $type = $product->getTypeInstance(true);
        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            foreach ($type->getUsedProducts(null, $product) as $child) {
                $children[] = ['id' => (int) $child->getId(), 'sku' => $child->getSku(), 'name' => $child->getName(), 'price' => (float) $child->getPrice()];
            }
        } elseif ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            foreach ($type->getAssociatedProducts($product) as $child) {
                $children[] = ['id' => (int) $child->getId(), 'sku' => $child->getSku(), 'name' => $child->getName(), 'price' => (float) $child->getPrice(), 'qty' => (float) $child->getQty()];
            }
        }
        $options = [];
        foreach ($product->getOptions() as $opt) {
            $values = [];
            foreach ((array) ($opt->getValues() ?: []) as $v) {
                $values[] = ['title' => $v->getTitle(), 'price' => (float) $v->getPrice(), 'sku' => $v->getSku()];
            }
            $options[] = ['title' => $opt->getTitle(), 'type' => $opt->getType(), 'required' => (bool) $opt->getIsRequire(), 'values' => $values];
        }
        $tier = [];
        foreach ((array) $product->getTierPrice() as $tp) {
            $tier[] = ['qty' => (float) $tp['price_qty'], 'price' => (float) $tp['price'], 'customer_group' => $tp['all_groups'] ? 'all' : (int) $tp['cust_group']];
        }
        return [
            'id' => (int) $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'type' => $product->getTypeId(),
            'attribute_set_id' => (int) $product->getAttributeSetId(),
            'status' => (int) $product->getStatus() === Mage_Catalog_Model_Product_Status::STATUS_ENABLED ? 'enabled' : 'disabled',
            'visibility' => (int) $product->getVisibility(),
            'url' => $storeId ? $product->getProductUrl() : null,
            'price' => round((float) $product->getPrice(), 2),
            'special_price' => $product->getSpecialPrice() !== null && $product->getSpecialPrice() !== '' ? round((float) $product->getSpecialPrice(), 2) : null,
            'special_from' => $product->getSpecialFromDate(),
            'special_to' => $product->getSpecialToDate(),
            'tier_prices' => $tier,
            'currency' => Mage::app()->getStore($storeId)->getBaseCurrencyCode(),
            'stock' => [
                'qty' => (float) $stock->getQty(),
                'is_in_stock' => (bool) $stock->getIsInStock(),
                'manage_stock' => (bool) $stock->getManageStock(),
                'min_qty' => (float) $stock->getMinQty(),
                'backorders' => (int) $stock->getBackorders(),
            ],
            'categories' => $categories,
            'website_ids' => array_map('intval', (array) $product->getWebsiteIds()),
            'short_description' => $product->getShortDescription(),
            'description' => $product->getDescription(),
            'meta_title' => $product->getMetaTitle(),
            'meta_keyword' => $product->getMetaKeyword(),
            'meta_description' => $product->getMetaDescription(),
            'url_key' => $product->getUrlKey(),
            'weight' => $product->getWeight() !== null ? (float) $product->getWeight() : null,
            'images' => $images,
            'custom_options' => $options,
            'children' => $children,
            'attributes' => $attributes,
            'created_at' => $product->getCreatedAt(),
            'updated_at' => $product->getUpdatedAt(),
        ];
    }
}
