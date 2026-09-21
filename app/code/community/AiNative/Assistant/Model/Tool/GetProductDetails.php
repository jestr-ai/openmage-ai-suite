<?php

class AiNative_Assistant_Model_Tool_GetProductDetails extends AiNative_Assistant_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'get_product_details';
    }

    public function getDescription(): string
    {
        return 'Full shopper-facing details for one product (by id or sku): description, attributes (material, color, size…), price, availability, reviews summary, and for configurable products the available options/variants. Use after search_catalog when the shopper asks about a specific item.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'id' => ['type' => 'integer'],
            'sku' => ['type' => 'string'],
        ]);
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $store = $this->store($context);
        $id = $this->int($args, 'id');
        if ($id <= 0 && ($sku = $this->str($args, 'sku')) !== '') {
            $id = (int) Mage::getModel('catalog/product')->getIdBySku($sku);
        }
        if ($id <= 0) {
            $this->fail('Provide id or sku.');
        }
        // Load through the visible collection so the price index is joined: grouped/bundle/configurable
        // parents have no own price and would otherwise report 0.
        $collection = $this->visibleCollection($store, $context)->addIdFilter([$id])->setPageSize(1);
        $product = $collection->getFirstItem();
        if (!$product->getId()) {
            $this->fail('Product not available in this store.');
        }
        $card = $this->productCard($product, $store);
        $product = Mage::getModel('catalog/product')->setStoreId((int) $store->getId())->load($id);
        $attributes = [];
        foreach ($product->getAttributes() as $attribute) {
            if (!$attribute->getIsVisibleOnFront() || $attribute->getAttributeCode() === 'description') {
                continue;
            }
            $value = $attribute->getFrontend()->getValue($product);
            if ($value !== null && $value !== '' && $value !== 'No' && !is_array($value)) {
                $attributes[$attribute->getStoreLabel() ?: $attribute->getFrontendLabel()] = (string) $value;
            }
        }
        $variants = [];
        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            foreach ($product->getTypeInstance(true)->getAssociatedProducts($product) as $child) {
                $variants[] = ['sku' => $child->getSku(), 'name' => $child->getName(), 'in_stock' => (bool) $child->isSaleable(), 'price' => $this->price((float) $child->getFinalPrice(), $store)];
            }
        }
        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $type = $product->getTypeInstance(true);
            $attrs = [];
            foreach ($type->getConfigurableAttributes($product) as $ca) {
                $attrs[] = $ca->getProductAttribute()->getAttributeCode();
            }
            foreach ($type->getUsedProducts(null, $product) as $child) {
                $opt = [];
                foreach ($attrs as $code) {
                    $opt[$code] = $child->getAttributeText($code) ?: $child->getData($code);
                }
                $variants[] = ['sku' => $child->getSku(), 'options' => $opt, 'in_stock' => (bool) $child->isSaleable(), 'price' => $this->price((float) $child->getFinalPrice(), $store)];
            }
        }
        $this->rememberProducts($context, [$card]);
        $reviews = Mage::getModel('review/review_summary')->setStoreId((int) $store->getId())->load($product->getId());
        return $card + [
            'description' => mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $product->getDescription())) ?? ''), 0, 1500),
            'attributes' => $attributes,
            'variants' => $variants,
            'reviews' => $reviews->getReviewsCount() ? ['count' => (int) $reviews->getReviewsCount(), 'rating_percent' => (int) $reviews->getRatingSummary()] : null,
            'categories' => array_values(array_filter(array_map(fn($c) => $c->getName(), iterator_to_array($product->getCategoryCollection()->addAttributeToSelect('name'))))),
        ];
    }
}
