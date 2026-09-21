<?php

class AiNative_Mcp_Model_Tool_Customer_SearchCustomers extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'search_customers';
    }

    public function getDescription(): string
    {
        return 'Search registered customers by email or name fragment, group, website, or signup date range. Returns id, name, email, group, website, created_at. Use get_customer for addresses and order history.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'query' => ['type' => 'string', 'description' => 'Email or name fragment'],
            'group_id' => ['type' => 'integer'],
            'website_id' => ['type' => 'integer'],
            'created_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'created_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            'page' => ['type' => 'integer', 'minimum' => 1],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/customer/manage';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $collection = Mage::getModel('customer/customer')->getCollection()
            ->addNameToSelect()
            ->addAttributeToSelect(['email', 'created_at', 'group_id', 'website_id', 'store_id'])
            ->setOrder('created_at', 'DESC');
        if (($q = $this->str($args, 'query')) !== '') {
            $like = '%' . $q . '%';
            $collection->getSelect()->where('e.email LIKE ? OR CONCAT_WS(" ", at_firstname.value, at_lastname.value) LIKE ?', $like, $like);
        }
        if (isset($args['group_id'])) {
            $collection->addAttributeToFilter('group_id', $this->int($args, 'group_id'));
        }
        if (isset($args['website_id'])) {
            $collection->addAttributeToFilter('website_id', $this->int($args, 'website_id'));
        }
        if (($f = $this->str($args, 'created_from')) !== '') {
            $collection->addAttributeToFilter('created_at', ['gteq' => $f . ' 00:00:00']);
        }
        if (($t = $this->str($args, 'created_to')) !== '') {
            $collection->addAttributeToFilter('created_at', ['lteq' => $t . ' 23:59:59']);
        }
        $groups = Mage::getResourceModel('customer/group_collection')->toOptionHash();
        return $this->paginate($collection, $this->page($args), $this->pageSize($args), fn(Mage_Customer_Model_Customer $c) => [
            'id' => (int) $c->getId(),
            'name' => $c->getName(),
            'email' => $c->getEmail(),
            'group' => $groups[(int) $c->getGroupId()] ?? (int) $c->getGroupId(),
            'website_id' => (int) $c->getWebsiteId(),
            'created_at' => $c->getCreatedAt(),
        ]);
    }
}
