<?php

class AiNative_Mcp_Model_Tool_Catalog_UpdateProduct extends AiNative_Core_Model_Tool_Abstract
{
    /** Attributes the model may change. Prices/status included; structural fields (type, attribute set, websites) excluded. */
    public const ALLOWED = ['name', 'description', 'short_description', 'meta_title', 'meta_keyword', 'meta_description', 'price', 'special_price', 'special_from_date', 'special_to_date', 'status', 'visibility', 'url_key', 'weight', 'news_from_date', 'news_to_date', 'manufacturer', 'color', 'size', 'country_of_manufacture', 'tax_class_id', 'msrp', 'cost'];

    public function getName(): string
    {
        return 'update_product';
    }

    public function getDescription(): string
    {
        return 'Update attributes of one product (by id or sku). Allowed: ' . implode(', ', self::ALLOWED) . '. status accepts "enabled"/"disabled". Pass store_id to write a store-view override; default writes the global/default value. Returns the changed fields. WRITE tool — confirm with the user first.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'id' => ['type' => 'integer'],
            'sku' => ['type' => 'string'],
            'store_id' => ['type' => 'integer', 'description' => 'Default 0 (global).'],
            'attributes' => ['type' => 'object', 'description' => 'Map attribute_code → new value', 'additionalProperties' => true],
        ], ['attributes']);
    }

    public function getAclResource(): ?string
    {
        return 'admin/catalog/products';
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $storeId = $this->int($args, 'store_id', 0, 0);
        $product = Mage::getModel('catalog/product')->setStoreId($storeId);
        $id = $this->int($args, 'id');
        if ($id <= 0 && ($sku = $this->str($args, 'sku')) !== '') {
            $id = (int) Mage::getModel('catalog/product')->getIdBySku($sku);
        }
        if ($id <= 0) {
            $this->fail('Provide id or sku.');
        }
        $product->load($id);
        if (!$product->getId()) {
            $this->fail('Product not found.');
        }
        if ($product->getOrigData() === null) {
            $product->setOrigData(); // EAV save needs original data to diff against
        }
        $changes = [];
        $rejected = [];
        foreach ((array) $args['attributes'] as $code => $value) {
            if (!in_array($code, self::ALLOWED, true)) {
                $rejected[] = $code;
                continue;
            }
            if ($code === 'status') {
                $value = in_array(strtolower((string) $value), ['enabled', '1', 'true'], true) ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED : Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
            }
            if (in_array($code, ['price', 'special_price', 'weight', 'msrp', 'cost'], true) && $value !== null && $value !== '') {
                if (!is_numeric($value) || (float) $value < 0) {
                    $this->fail("{$code} must be a non-negative number.");
                }
                $value = (float) $value;
            }
            $before = $product->getData($code);
            if ((string) $before === (string) $value) {
                continue;
            }
            $product->setData($code, $value === '' ? null : $value);
            $changes[$code] = ['from' => $before, 'to' => $value];
        }
        if (!$changes) {
            return ['id' => (int) $product->getId(), 'sku' => $product->getSku(), 'changed' => [], 'rejected' => $rejected, 'message' => 'Nothing to change.'];
        }
        $product->setIsMassupdate(true)->setExcludeUrlRewrite(false);
        $product->save();
        Mage::helper('ainative_core')->log(sprintf('update_product #%d by %s', $product->getId(), $context->getActorLabel()), $changes);
        return ['id' => (int) $product->getId(), 'sku' => $product->getSku(), 'store_id' => $storeId, 'changed' => $changes, 'rejected' => $rejected];
    }
}
