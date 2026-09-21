<?php

class AiNative_Assistant_Model_Tool_AddToCart extends AiNative_Assistant_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'add_to_cart';
    }

    public function getDescription(): string
    {
        return 'Propose adding a simple product (no required options) to the shopper\'s cart. This does NOT add it: it returns an add-to-cart card the shopper must click. For configurable products or items with options, link to the product page instead.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'product_id' => ['type' => 'integer'],
            'qty' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
        ], ['product_id']);
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $store = $this->store($context);
        if (!Mage::helper('ainative_assistant')->flag('allow_add_to_cart', (int) $store->getId())) {
            $this->fail('Add to cart from chat is disabled; link to the product page instead.');
        }
        $product = Mage::getModel('catalog/product')->setStoreId((int) $store->getId())->load($this->int($args, 'product_id'));
        if (!$product->getId() || !$product->isSaleable()) {
            $this->fail('Product is not available for purchase.');
        }
        $card = $this->productCard($product, $store);
        if (!$card['can_add_to_cart']) {
            return ['can_add_to_cart' => false, 'message' => 'This product needs options to be chosen; send the shopper to the product page.', 'product' => $card];
        }
        $qty = $this->int($args, 'qty', 1, 1, 20);
        $card['qty'] = $qty;
        $card['add_to_cart_url'] = Mage::helper('checkout/cart')->getAddUrl($product, ['qty' => $qty]);
        $cards = $context->getMeta('cart_cards', []);
        $cards[] = $card;
        $context->withMeta('cart_cards', $cards);
        return ['can_add_to_cart' => true, 'message' => 'An add-to-cart button was shown to the shopper. Tell them to click it to add the item.', 'product' => $card];
    }
}
