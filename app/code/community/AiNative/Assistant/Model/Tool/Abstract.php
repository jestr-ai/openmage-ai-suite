<?php

/**
 * Base for storefront tools: store-scoped, customer-visible data only.
 */
abstract class AiNative_Assistant_Model_Tool_Abstract extends AiNative_Core_Model_Tool_Abstract
{
    public function getAclResource(): ?string
    {
        return null;
    }

    protected function productCard(Mage_Catalog_Model_Product $p, Mage_Core_Model_Store $store): array
    {
        [$final, $regular, $isFrom] = $this->resolvePrices($p);
        $image = null;
        try {
            if ($p->getSmallImage() && $p->getSmallImage() !== 'no_selection') {
                $image = (string) Mage::helper('catalog/image')->init($p, 'small_image')->resize(240);
            }
        } catch (Throwable) {
        }
        return [
            'id' => (int) $p->getId(),
            'sku' => $p->getSku(),
            'name' => $p->getName(),
            'type' => $p->getTypeId(),
            'price' => $this->price($final, $store),
            'price_is_from' => $isFrom,
            'regular_price' => $regular > $final ? $this->price($regular, $store) : null,
            'on_sale' => $regular > $final,
            'in_stock' => (bool) $p->isSaleable(),
            'url' => $p->getProductUrl(),
            'image' => $image,
            'short_description' => mb_substr(trim(strip_tags((string) $p->getShortDescription())), 0, 240),
            'can_add_to_cart' => $p->isSaleable() && $p->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_SIMPLE && !$p->getTypeInstance(true)->hasRequiredOptions($p),
        ];
    }

    /**
     * Grouped, bundle and configurable products have no own price: getFinalPrice() returns 0 while the
     * price index holds the real range. Fall back to the index and flag the price as a "from" price.
     *
     * @return array{0: float, 1: float, 2: bool} final, regular, isFromPrice
     */
    protected function resolvePrices(Mage_Catalog_Model_Product $p): array
    {
        // Prefer the price index (already customer-group and website scoped by addPriceData): it covers
        // grouped/bundle/configurable parents, and avoids the price model, which touches the customer session.
        $indexFinal = $p->getData('final_price') !== null ? (float) $p->getData('final_price') : null;
        $indexMin = $p->getData('min_price') !== null ? (float) $p->getData('min_price') : null;
        $indexMax = $p->getData('max_price') !== null ? (float) $p->getData('max_price') : null;
        $regular = (float) $p->getData('price');
        $final = $indexFinal !== null && $indexFinal > 0.0 ? $indexFinal : 0.0;
        if ($final <= 0.0 && $indexMin !== null && $indexMin > 0.0) {
            $final = $indexMin;
        }
        if ($final <= 0.0 && $indexFinal === null && $indexMin === null) {
            $final = (float) $p->getFinalPrice(); // collection without price data
        }
        if ($final <= 0.0) {
            $final = $regular;
        }
        if ($regular <= 0.0) {
            $regular = $final;
        }
        $isFrom = $indexMin !== null && $indexMax !== null && $indexMax > $indexMin + 0.0001;
        if (!$isFrom && in_array($p->getTypeId(), [Mage_Catalog_Model_Product_Type::TYPE_GROUPED, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE], true)) {
            $isFrom = true;
        }
        return [$final, $regular, $isFrom];
    }

    /**
     * Products a shopper may see: enabled, visible in catalog/search, in this store's website.
     */
    protected function visibleCollection(Mage_Core_Model_Store $store, ?AiNative_Core_Model_Tool_Context $context = null): Mage_Catalog_Model_Resource_Product_Collection
    {
        $groupId = $context && $context->getCustomer()
            ? (int) $context->getCustomer()->getGroupId()
            : Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId((int) $store->getId())
            ->addStoreFilter($store)
            ->addWebsiteFilter($store->getWebsiteId())
            ->addAttributeToSelect(['name', 'sku', 'price', 'special_price', 'special_from_date', 'special_to_date', 'small_image', 'short_description', 'url_key', 'url_path', 'type_id', 'required_options', 'tax_class_id'])
            ->addPriceData($groupId, (int) $store->getWebsiteId())->addTaxPercents()->addUrlRewrite();
        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInSearchFilterToCollection($collection);
        if (!Mage::helper('cataloginventory')->isShowOutOfStock()) {
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        }
        return $collection;
    }
}
