<?php

class AiNative_Mcp_Model_Tool_Customer_GetCustomer extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'get_customer';
    }

    public function getDescription(): string
    {
        return 'One customer by id or exact email: profile, group, addresses, lifetime order count and total, and the 10 most recent orders (order_number, date, status, total).';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'id' => ['type' => 'integer'],
            'email' => ['type' => 'string'],
            'website_id' => ['type' => 'integer', 'description' => 'Needed with email when accounts are per-website.'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/customer/manage';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $customer = Mage::getModel('customer/customer');
        if (($id = $this->int($args, 'id')) > 0) {
            $customer->load($id);
        } elseif (($email = $this->str($args, 'email')) !== '') {
            $websiteId = $this->int($args, 'website_id');
            if ($websiteId <= 0) {
                $websiteId = (int) Mage::app()->getWebsite(true)->getId();
            }
            $customer->setWebsiteId($websiteId)->loadByEmail($email);
            if (!$customer->getId()) {
                $found = Mage::getModel('customer/customer')->getCollection()->addAttributeToFilter('email', $email)->getFirstItem();
                if ($found->getId()) {
                    $customer->load((int) $found->getId());
                }
            }
        } else {
            $this->fail('Provide id or email.');
        }
        if (!$customer->getId()) {
            $this->fail('Customer not found.');
        }
        $addresses = [];
        foreach ($customer->getAddresses() as $a) {
            $addresses[] = [
                'id' => (int) $a->getId(),
                'name' => $a->getName(),
                'street' => implode(', ', (array) $a->getStreet()),
                'city' => $a->getCity(),
                'region' => $a->getRegion(),
                'postcode' => $a->getPostcode(),
                'country' => $a->getCountryId(),
                'telephone' => $a->getTelephone(),
                'default_billing' => (int) $a->getId() === (int) $customer->getDefaultBilling(),
                'default_shipping' => (int) $a->getId() === (int) $customer->getDefaultShipping(),
            ];
        }
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect(['increment_id', 'created_at', 'status', 'grand_total', 'order_currency_code'])
            ->addFieldToFilter('customer_id', $customer->getId())
            ->setOrder('created_at', 'DESC');
        $lifetime = $orders->getConnection()->fetchRow(
            $orders->getConnection()->select()->from($orders->getMainTable(), ['cnt' => 'COUNT(*)', 'total' => 'SUM(base_grand_total)'])
                ->where('customer_id = ?', $customer->getId())->where('state NOT IN (?)', [Mage_Sales_Model_Order::STATE_CANCELED]),
        );
        $orders->setPageSize(10);
        $recent = [];
        foreach ($orders as $o) {
            $recent[] = ['order_number' => $o->getIncrementId(), 'created_at' => $o->getCreatedAt(), 'status' => $o->getStatus(), 'grand_total' => round((float) $o->getGrandTotal(), 2), 'currency' => $o->getOrderCurrencyCode()];
        }
        return [
            'id' => (int) $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'group_id' => (int) $customer->getGroupId(),
            'website_id' => (int) $customer->getWebsiteId(),
            'store_id' => (int) $customer->getStoreId(),
            'created_at' => $customer->getCreatedAt(),
            'dob' => $customer->getDob(),
            'gender' => $customer->getGender(),
            'taxvat' => $customer->getTaxvat(),
            'is_subscribed' => (bool) Mage::getModel('newsletter/subscriber')->loadByCustomer($customer)->isSubscribed(),
            'addresses' => $addresses,
            'lifetime' => ['orders' => (int) ($lifetime['cnt'] ?? 0), 'total' => round((float) ($lifetime['total'] ?? 0), 2)],
            'recent_orders' => $recent,
        ];
    }
}
