<?php

class AiNative_Mcp_Model_Tool_Sales_ListOrders extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'list_orders';
    }

    public function getDescription(): string
    {
        return 'List orders newest first with filters: status, state, created date range (YYYY-MM-DD, store timezone), customer email, customer name fragment, order number (increment id) fragment, store, min grand total. Returns compact rows (order_number, date, status, customer, grand_total, items_count). Use get_order for details.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'status' => ['type' => 'string', 'description' => 'e.g. pending, processing, complete, canceled, holded, closed'],
            'state' => ['type' => 'string', 'description' => 'new, processing, complete, canceled, closed, holded, payment_review'],
            'from' => ['type' => 'string', 'description' => 'Created on/after, YYYY-MM-DD'],
            'to' => ['type' => 'string', 'description' => 'Created on/before, YYYY-MM-DD'],
            'customer_email' => ['type' => 'string'],
            'customer_name' => ['type' => 'string'],
            'order_number' => ['type' => 'string', 'description' => 'Increment id or fragment'],
            'store_id' => ['type' => 'integer'],
            'min_total' => ['type' => 'number'],
            'page' => ['type' => 'integer', 'minimum' => 1],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/sales/order';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        /** @var Mage_Sales_Model_Resource_Order_Collection $collection */
        $collection = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect(['entity_id', 'increment_id', 'created_at', 'updated_at', 'status', 'state', 'store_id', 'customer_email', 'customer_firstname', 'customer_lastname', 'customer_id', 'grand_total', 'base_grand_total', 'order_currency_code', 'total_item_count', 'total_qty_ordered', 'shipping_description'])
            ->setOrder('created_at', 'DESC');
        if (($s = $this->str($args, 'status')) !== '') {
            $collection->addFieldToFilter('status', $s);
        }
        if (($s = $this->str($args, 'state')) !== '') {
            $collection->addFieldToFilter('state', $s);
        }
        $this->applyDateRange($collection, $args);
        if (($e = $this->str($args, 'customer_email')) !== '') {
            $collection->addFieldToFilter('customer_email', ['like' => '%' . $e . '%']);
        }
        if (($n = $this->str($args, 'customer_name')) !== '') {
            $collection->getSelect()->where('CONCAT_WS(" ", main_table.customer_firstname, main_table.customer_lastname) LIKE ?', '%' . $n . '%');
        }
        if (($o = $this->str($args, 'order_number')) !== '') {
            $collection->addFieldToFilter('increment_id', ['like' => '%' . $o . '%']);
        }
        if (isset($args['store_id'])) {
            $collection->addFieldToFilter('store_id', $this->int($args, 'store_id'));
        }
        if (isset($args['min_total'])) {
            $collection->addFieldToFilter('grand_total', ['gteq' => (float) $args['min_total']]);
        }
        return $this->paginate($collection, $this->page($args), $this->pageSize($args), fn(Mage_Sales_Model_Order $o) => [
            'order_number' => $o->getIncrementId(),
            'id' => (int) $o->getId(),
            'created_at' => $o->getCreatedAt(),
            'status' => $o->getStatus(),
            'state' => $o->getState(),
            'store_id' => (int) $o->getStoreId(),
            'customer' => trim($o->getCustomerFirstname() . ' ' . $o->getCustomerLastname()),
            'customer_email' => $o->getCustomerEmail(),
            'customer_id' => $o->getCustomerId() ? (int) $o->getCustomerId() : null,
            'grand_total' => round((float) $o->getGrandTotal(), 2),
            'currency' => $o->getOrderCurrencyCode(),
            'items_count' => (int) $o->getTotalItemCount(),
            'qty_ordered' => (float) $o->getTotalQtyOrdered(),
            'shipping' => $o->getShippingDescription(),
        ]);
    }

    protected function applyDateRange(Varien_Data_Collection_Db $collection, array $args, string $field = 'created_at'): void
    {
        $tz = $this->storeTimezone(isset($args['store_id']) ? $this->int($args, 'store_id') : null);
        if (($from = $this->str($args, 'from')) !== '') {
            $collection->addFieldToFilter($field, ['gteq' => $this->toUtc($from . ' 00:00:00', $tz)]);
        }
        if (($to = $this->str($args, 'to')) !== '') {
            $collection->addFieldToFilter($field, ['lteq' => $this->toUtc($to . ' 23:59:59', $tz)]);
        }
    }

    protected function toUtc(string $local, string $tz): string
    {
        try {
            $dt = new DateTime($local, new DateTimeZone($tz));
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable) {
            $this->fail('Invalid date: ' . $local);
        }
    }
}
