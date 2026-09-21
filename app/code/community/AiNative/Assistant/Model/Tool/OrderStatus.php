<?php

class AiNative_Assistant_Model_Tool_OrderStatus extends AiNative_Assistant_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'order_status';
    }

    public function getDescription(): string
    {
        return 'Look up the shopper\'s order(s). Logged-in shoppers: call without arguments for their recent orders, or with order_number for one. Guests: BOTH order_number and the e-mail used at checkout are required; never look up an order without them. Returns status, items, shipments with tracking, and totals.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'order_number' => ['type' => 'string'],
            'email' => ['type' => 'string', 'description' => 'Required for guests.'],
        ]);
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $store = $this->store($context);
        if (!Mage::helper('ainative_assistant')->flag('allow_order_lookup', (int) $store->getId())) {
            $this->fail('Order lookup is not available in chat. Please use the account page or contact support.');
        }
        $customer = $context->getCustomer();
        $number = $this->str($args, 'order_number');
        $orders = [];
        if ($customer) {
            $collection = Mage::getResourceModel('sales/order_collection')->addFieldToFilter('customer_id', (int) $customer->getId())->setOrder('created_at', 'DESC');
            if ($number !== '') {
                $collection->addFieldToFilter('increment_id', $number);
            } else {
                $collection->setPageSize(3);
            }
            foreach ($collection as $o) {
                $orders[] = $this->exportOrder($o);
            }
            if (!$orders) {
                $this->fail($number !== '' ? 'No order with that number on your account.' : 'You have no orders yet.');
            }
        } else {
            $email = $this->str($args, 'email');
            if ($number === '' || $email === '') {
                $this->fail('Ask the shopper for both the order number and the e-mail address used for the order.');
            }
            Mage::getSingleton('ainative_core/rateLimit')->hit('order_lookup:' . $context->getRateKey(), 5);
            $order = Mage::getModel('sales/order')->loadByIncrementId($number);
            if (!$order->getId() || strcasecmp((string) $order->getCustomerEmail(), $email) !== 0) {
                $this->fail('No order matches that number and e-mail. Double-check both (the e-mail must be the one used at checkout).');
            }
            $orders[] = $this->exportOrder($order);
        }
        $context->withMeta('order_cards', $orders);
        return ['orders' => $orders];
    }

    private function exportOrder(Mage_Sales_Model_Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $i) {
            $items[] = ['name' => $i->getName(), 'sku' => $i->getSku(), 'qty' => (float) $i->getQtyOrdered(), 'shipped' => (float) $i->getQtyShipped(), 'refunded' => (float) $i->getQtyRefunded()];
        }
        $tracks = [];
        foreach ($order->getTracksCollection() as $t) {
            $tracks[] = ['carrier' => $t->getTitle(), 'number' => $t->getNumber(), 'url' => Mage::getUrl('sales/guest/form') ];
        }
        $statusLabel = $order->getStatusLabel();
        $friendly = match ($order->getState()) {
            Mage_Sales_Model_Order::STATE_NEW => 'received, awaiting processing',
            Mage_Sales_Model_Order::STATE_PROCESSING => 'being prepared',
            Mage_Sales_Model_Order::STATE_COMPLETE => 'shipped / completed',
            Mage_Sales_Model_Order::STATE_CANCELED => 'canceled',
            Mage_Sales_Model_Order::STATE_CLOSED => 'refunded / closed',
            Mage_Sales_Model_Order::STATE_HOLDED => 'on hold',
            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW => 'payment under review',
            default => (string) $statusLabel,
        };
        return [
            'order_number' => $order->getIncrementId(),
            'placed_at' => Mage::helper('core')->formatDate($order->getCreatedAt(), 'medium', false),
            'status' => $statusLabel,
            'status_plain' => $friendly,
            'items' => $items,
            'shipping_method' => $order->getShippingDescription(),
            'ship_to' => $order->getShippingAddress() ? trim($order->getShippingAddress()->getCity() . ', ' . $order->getShippingAddress()->getCountryId()) : null,
            'total' => $order->formatPrice((float) $order->getGrandTotal()),
            'tracking' => $tracks,
            'has_shipment' => $order->hasShipments(),
        ];
    }
}
