<?php

class AiNative_Mcp_Model_Tool_Sales_GetOrder extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'get_order';
    }

    public function getDescription(): string
    {
        return 'Full detail of one order by order number (increment id, e.g. 100000123) or entity id: customer, billing/shipping addresses, items (sku, name, qty ordered/shipped/invoiced/refunded, prices), totals, payment method, invoices, shipments with tracking numbers, credit memos, and the status/comment history.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'order_number' => ['type' => 'string'],
            'id' => ['type' => 'integer'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/sales/order/actions/view';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $order = $this->loadOrder($args);
        return $this->export($order);
    }

    protected function loadOrder(array $args): Mage_Sales_Model_Order
    {
        $order = Mage::getModel('sales/order');
        if (($num = $this->str($args, 'order_number')) !== '') {
            $order->loadByIncrementId($num);
        } elseif (($id = $this->int($args, 'id')) > 0) {
            $order->load($id);
        } else {
            $this->fail('Provide order_number or id.');
        }
        if (!$order->getId()) {
            $this->fail('Order not found.');
        }
        return $order;
    }

    public function export(Mage_Sales_Model_Order $order, bool $includeHistory = true): array
    {
        $addr = fn(?Mage_Sales_Model_Order_Address $a) => $a ? [
            'name' => $a->getName(),
            'company' => $a->getCompany(),
            'street' => implode(', ', (array) $a->getStreet()),
            'city' => $a->getCity(),
            'region' => $a->getRegion(),
            'postcode' => $a->getPostcode(),
            'country' => $a->getCountryId(),
            'telephone' => $a->getTelephone(),
        ] : null;
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'product_id' => (int) $item->getProductId(),
                'type' => $item->getProductType(),
                'qty_ordered' => (float) $item->getQtyOrdered(),
                'qty_shipped' => (float) $item->getQtyShipped(),
                'qty_invoiced' => (float) $item->getQtyInvoiced(),
                'qty_refunded' => (float) $item->getQtyRefunded(),
                'qty_canceled' => (float) $item->getQtyCanceled(),
                'price' => round((float) $item->getPrice(), 2),
                'row_total' => round((float) $item->getRowTotal(), 2),
                'discount' => round((float) $item->getDiscountAmount(), 2),
                'tax' => round((float) $item->getTaxAmount(), 2),
            ];
        }
        $shipments = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            $tracks = [];
            foreach ($shipment->getAllTracks() as $t) {
                $tracks[] = ['carrier' => $t->getTitle(), 'code' => $t->getCarrierCode(), 'number' => $t->getNumber()];
            }
            $shipments[] = ['number' => $shipment->getIncrementId(), 'created_at' => $shipment->getCreatedAt(), 'qty' => (float) $shipment->getTotalQty(), 'tracks' => $tracks];
        }
        $invoices = [];
        foreach ($order->getInvoiceCollection() as $inv) {
            $invoices[] = ['number' => $inv->getIncrementId(), 'created_at' => $inv->getCreatedAt(), 'state' => (int) $inv->getState(), 'grand_total' => round((float) $inv->getGrandTotal(), 2)];
        }
        $creditmemos = [];
        foreach ($order->getCreditmemosCollection() as $cm) {
            $creditmemos[] = ['number' => $cm->getIncrementId(), 'created_at' => $cm->getCreatedAt(), 'grand_total' => round((float) $cm->getGrandTotal(), 2)];
        }
        $history = [];
        if ($includeHistory) {
            foreach ($order->getStatusHistoryCollection() as $h) {
                if ($h->getComment() || $h->getStatus()) {
                    $history[] = ['created_at' => $h->getCreatedAt(), 'status' => $h->getStatus(), 'comment' => $h->getComment(), 'customer_notified' => (bool) $h->getIsCustomerNotified(), 'visible_on_front' => (bool) $h->getIsVisibleOnFront()];
                }
            }
        }
        $payment = $order->getPayment();
        return [
            'order_number' => $order->getIncrementId(),
            'id' => (int) $order->getId(),
            'store_id' => (int) $order->getStoreId(),
            'store_name' => $order->getStoreName(),
            'created_at' => $order->getCreatedAt(),
            'updated_at' => $order->getUpdatedAt(),
            'status' => $order->getStatus(),
            'state' => $order->getState(),
            'customer' => [
                'id' => $order->getCustomerId() ? (int) $order->getCustomerId() : null,
                'name' => $order->getCustomerName(),
                'email' => $order->getCustomerEmail(),
                'is_guest' => (bool) $order->getCustomerIsGuest(),
                'group_id' => (int) $order->getCustomerGroupId(),
            ],
            'billing_address' => $addr($order->getBillingAddress()),
            'shipping_address' => $addr($order->getShippingAddress()),
            'shipping_method' => $order->getShippingMethod(),
            'shipping_description' => $order->getShippingDescription(),
            'payment' => $payment ? ['method' => $payment->getMethod(), 'amount_paid' => round((float) $payment->getAmountPaid(), 2), 'amount_ordered' => round((float) $payment->getAmountOrdered(), 2), 'last_trans_id' => $payment->getLastTransId()] : null,
            'currency' => $order->getOrderCurrencyCode(),
            'totals' => [
                'subtotal' => round((float) $order->getSubtotal(), 2),
                'discount' => round((float) $order->getDiscountAmount(), 2),
                'discount_description' => $order->getDiscountDescription(),
                'coupon_code' => $order->getCouponCode(),
                'shipping' => round((float) $order->getShippingAmount(), 2),
                'tax' => round((float) $order->getTaxAmount(), 2),
                'grand_total' => round((float) $order->getGrandTotal(), 2),
                'total_paid' => round((float) $order->getTotalPaid(), 2),
                'total_refunded' => round((float) $order->getTotalRefunded(), 2),
                'total_due' => round((float) $order->getTotalDue(), 2),
            ],
            'items' => $items,
            'invoices' => $invoices,
            'shipments' => $shipments,
            'creditmemos' => $creditmemos,
            'history' => $history,
        ];
    }
}
