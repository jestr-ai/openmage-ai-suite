<?php

class AiNative_Mcp_Model_Tool_Content_ListCartPriceRules extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'list_cart_price_rules';
    }

    public function getDescription(): string
    {
        return 'List shopping-cart price rules (promotions/coupons): name, description, active flag, date range, coupon type and code, discount action and amount, customer groups, websites, usage limits. Default: active rules valid today.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'include_inactive' => ['type' => 'boolean'],
            'coupon_code' => ['type' => 'string', 'description' => 'Find the rule for a specific coupon code.'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/promo/quote';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $collection = Mage::getModel('salesrule/rule')->getCollection()->setOrder('sort_order', 'ASC');
        if (!$this->bool($args, 'include_inactive')) {
            $today = Mage::app()->getLocale()->date()->toString('yyyy-MM-dd');
            $collection->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('from_date', [['lteq' => $today], ['null' => true]])
                ->addFieldToFilter('to_date', [['gteq' => $today], ['null' => true]]);
        }
        if (($code = $this->str($args, 'coupon_code')) !== '') {
            $coupon = Mage::getModel('salesrule/coupon')->load($code, 'code');
            if (!$coupon->getId()) {
                return ['rules' => [], 'message' => 'No rule has this coupon code.'];
            }
            $collection->addFieldToFilter('rule_id', $coupon->getRuleId());
        }
        $actions = ['by_percent' => 'percent of price', 'by_fixed' => 'fixed amount', 'cart_fixed' => 'fixed amount for whole cart', 'buy_x_get_y' => 'buy X get Y free'];
        $rules = [];
        foreach ($collection as $rule) {
            $rules[] = [
                'rule_id' => (int) $rule->getId(),
                'name' => $rule->getName(),
                'description' => $rule->getDescription(),
                'is_active' => (bool) $rule->getIsActive(),
                'from_date' => $rule->getFromDate(),
                'to_date' => $rule->getToDate(),
                'coupon_type' => match ((int) $rule->getCouponType()) { 1 => 'no_coupon', 2 => 'specific', 3 => 'auto', default => (int) $rule->getCouponType() },
                'coupon_code' => $rule->getCouponCode(),
                'uses_per_coupon' => (int) $rule->getUsesPerCoupon(),
                'uses_per_customer' => (int) $rule->getUsesPerCustomer(),
                'action' => $actions[$rule->getSimpleAction()] ?? $rule->getSimpleAction(),
                'discount_amount' => (float) $rule->getDiscountAmount(),
                'discount_qty' => $rule->getDiscountQty() !== null ? (float) $rule->getDiscountQty() : null,
                'free_shipping' => (int) $rule->getSimpleFreeShipping(),
                'stop_rules_processing' => (bool) $rule->getStopRulesProcessing(),
                'customer_group_ids' => array_map('intval', (array) $rule->getCustomerGroupIds()),
                'website_ids' => array_map('intval', (array) $rule->getWebsiteIds()),
                'times_used' => (int) $rule->getTimesUsed(),
            ];
        }
        return ['count' => count($rules), 'rules' => $rules];
    }
}
