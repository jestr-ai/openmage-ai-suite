<?php

class AiNative_Mcp_Model_Tool_Catalog_UpdateStock extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'update_stock';
    }

    public function getDescription(): string
    {
        return 'Set inventory for one product (by id or sku): absolute qty and/or is_in_stock flag, or adjust qty by a delta. Returns before/after. WRITE tool — confirm with the user first.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'id' => ['type' => 'integer'],
            'sku' => ['type' => 'string'],
            'qty' => ['type' => 'number', 'description' => 'New absolute quantity.'],
            'adjust_by' => ['type' => 'number', 'description' => 'Relative change (+/-). Ignored if qty is given.'],
            'is_in_stock' => ['type' => 'boolean'],
        ]);
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
        $id = $this->int($args, 'id');
        if ($id <= 0 && ($sku = $this->str($args, 'sku')) !== '') {
            $id = (int) Mage::getModel('catalog/product')->getIdBySku($sku);
        }
        if ($id <= 0) {
            $this->fail('Provide id or sku.');
        }
        $product = Mage::getModel('catalog/product')->load($id);
        if (!$product->getId()) {
            $this->fail('Product not found.');
        }
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        $before = ['qty' => (float) $stock->getQty(), 'is_in_stock' => (bool) $stock->getIsInStock()];
        if (isset($args['qty'])) {
            $stock->setQty((float) $args['qty']);
        } elseif (isset($args['adjust_by'])) {
            $stock->setQty((float) $stock->getQty() + (float) $args['adjust_by']);
        }
        if ((float) $stock->getQty() < 0) {
            $this->fail('Quantity cannot be negative.');
        }
        if (array_key_exists('is_in_stock', $args)) {
            $stock->setIsInStock($this->bool($args, 'is_in_stock') ? 1 : 0);
        } elseif (isset($args['qty']) || isset($args['adjust_by'])) {
            $stock->setIsInStock((float) $stock->getQty() > (float) $stock->getMinQty() ? 1 : 0);
        }
        if (!$stock->getProductId()) {
            $stock->setProductId($product->getId())->setStockId(1);
        }
        $stock->save();
        $after = ['qty' => (float) $stock->getQty(), 'is_in_stock' => (bool) $stock->getIsInStock()];
        Mage::helper('ainative_core')->log(sprintf('update_stock #%d by %s', $product->getId(), $context->getActorLabel()), ['before' => $before, 'after' => $after]);
        return ['id' => (int) $product->getId(), 'sku' => $product->getSku(), 'before' => $before, 'after' => $after];
    }
}
