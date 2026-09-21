<?php

class AiNative_Assistant_Model_Tool_CurrentPromotions extends AiNative_Assistant_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'current_promotions';
    }

    public function getDescription(): string
    {
        return 'Active promotions the shopper can use today: public cart price rules (name, description, coupon code if it is a public code, discount summary) plus a few products currently on sale. Only rules valid for the shopper\'s customer group are shown.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([]);
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $store = $this->store($context);
        $groupId = $context->getCustomer() ? (int) $context->getCustomer()->getGroupId() : Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        $today = Mage::app()->getLocale()->date()->toString('yyyy-MM-dd');
        $rules = Mage::getModel('salesrule/rule')->getCollection()
            ->addFieldToFilter('is_active', 1)
            ->addWebsiteGroupDateFilter((int) $store->getWebsiteId(), $groupId, $today)
            ->setOrder('sort_order', 'ASC');
        $out = [];
        foreach ($rules as $rule) {
            $type = (int) $rule->getCouponType();
            // auto-generated coupons (type 3) and hidden specific codes are not public knowledge; show name/description only.
            $out[] = [
                'name' => $rule->getName(),
                'description' => $rule->getDescription(),
                'coupon_code' => $type === 2 && $rule->getDescription() && stripos((string) $rule->getDescription(), (string) $rule->getCouponCode()) !== false ? $rule->getCouponCode() : null,
                'needs_coupon' => $type !== 1,
                'discount' => match ($rule->getSimpleAction()) {
                    'by_percent' => rtrim(rtrim(number_format((float) $rule->getDiscountAmount(), 2), '0'), '.') . '% off',
                    'by_fixed' => $this->price((float) $rule->getDiscountAmount(), $store) . ' off per item',
                    'cart_fixed' => $this->price((float) $rule->getDiscountAmount(), $store) . ' off the cart',
                    'buy_x_get_y' => 'buy X get Y free',
                    default => null,
                },
                'free_shipping' => (int) $rule->getSimpleFreeShipping() > 0,
                'ends' => $rule->getToDate(),
            ];
        }
        $collection = $this->visibleCollection($store, $context);
        $collection->getSelect()->where('price_index.final_price < price_index.price')->order(new Zend_Db_Expr('(price_index.price - price_index.final_price) / price_index.price DESC'));
        $collection->setPageSize(6);
        $sale = [];
        foreach ($collection as $p) {
            $sale[] = $this->productCard($p, $store);
        }
        $this->rememberProducts($context, $sale);
        return ['promotions' => $out, 'on_sale_products' => $sale, 'note' => 'Coupon codes are only shown when the merchant published them in the promotion description.'];
    }
}
