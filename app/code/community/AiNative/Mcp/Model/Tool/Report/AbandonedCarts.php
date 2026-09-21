<?php

class AiNative_Mcp_Model_Tool_Report_AbandonedCarts extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'abandoned_carts';
    }

    public function getDescription(): string
    {
        return 'Active, non-converted customer quotes (abandoned carts) with items, updated in the last N days: customer name/email, items count, subtotal, last updated, coupon. Guest carts are excluded unless include_guests.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'description' => 'Look-back window in days. Default 14.'],
            'min_subtotal' => ['type' => 'number'],
            'include_guests' => ['type' => 'boolean'],
            'store_id' => ['type' => 'integer'],
            'page' => ['type' => 'integer', 'minimum' => 1],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/report/shopcart/abandoned';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $days = $this->int($args, 'days', 14, 1, 365);
        /** @var Mage_Sales_Model_Resource_Quote_Collection $collection */
        $collection = Mage::getResourceModel('sales/quote_collection')
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('items_count', ['gt' => 0])
            ->addFieldToFilter('updated_at', ['gteq' => date('Y-m-d H:i:s', time() - $days * 86400)])
            ->setOrder('updated_at', 'DESC');
        if (!$this->bool($args, 'include_guests')) {
            $collection->addFieldToFilter('customer_id', ['notnull' => true]);
        }
        if (isset($args['min_subtotal'])) {
            $collection->addFieldToFilter('subtotal', ['gteq' => (float) $args['min_subtotal']]);
        }
        if (isset($args['store_id'])) {
            $collection->addFieldToFilter('store_id', $this->int($args, 'store_id'));
        }
        return $this->paginate($collection, $this->page($args), $this->pageSize($args), function (Mage_Sales_Model_Quote $q) {
            $items = [];
            foreach ($q->getAllVisibleItems() as $item) {
                $items[] = ['sku' => $item->getSku(), 'name' => $item->getName(), 'qty' => (float) $item->getQty(), 'price' => round((float) $item->getPrice(), 2)];
            }
            return [
                'quote_id' => (int) $q->getId(),
                'customer_id' => $q->getCustomerId() ? (int) $q->getCustomerId() : null,
                'customer' => trim((string) ($q->getCustomerFirstname() . ' ' . $q->getCustomerLastname())) ?: null,
                'email' => $q->getCustomerEmail(),
                'store_id' => (int) $q->getStoreId(),
                'items_count' => (int) $q->getItemsCount(),
                'items_qty' => (float) $q->getItemsQty(),
                'subtotal' => round((float) $q->getSubtotal(), 2),
                'coupon_code' => $q->getCouponCode(),
                'updated_at' => $q->getUpdatedAt(),
                'items' => $items,
            ];
        });
    }
}
