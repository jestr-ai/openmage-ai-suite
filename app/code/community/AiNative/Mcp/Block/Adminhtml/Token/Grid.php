<?php

class AiNative_Mcp_Block_Adminhtml_Token_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('ainativeTokenGrid');
        $this->setDefaultSort('token_id');
        $this->setDefaultDir('DESC');
    }

    protected function _prepareCollection()
    {
        $this->setCollection(Mage::getResourceModel('ainative_mcp/token_collection')->joinAdminUser());
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $h = Mage::helper('ainative_core');
        $this->addColumn('token_id', ['header' => 'ID', 'index' => 'token_id', 'width' => '50px']);
        $this->addColumn('name', ['header' => $h->__('Name'), 'index' => 'name']);
        $this->addColumn('token_prefix', ['header' => $h->__('Token'), 'index' => 'token_prefix', 'renderer' => 'ainative_mcp/adminhtml_token_grid_renderer_prefix', 'width' => '120px']);
        $this->addColumn('username', ['header' => $h->__('Runs as Admin'), 'index' => 'username', 'filter_index' => 'u.username']);
        $this->addColumn('scope', ['header' => $h->__('Scope'), 'index' => 'scope', 'type' => 'options', 'options' => ['read' => 'read', 'write' => 'read + write'], 'width' => '90px']);
        $this->addColumn('allowed_tools', ['header' => $h->__('Tools'), 'index' => 'allowed_tools', 'renderer' => 'ainative_mcp/adminhtml_token_grid_renderer_tools', 'filter' => false]);
        $this->addColumn('is_active', ['header' => $h->__('Active'), 'index' => 'is_active', 'type' => 'options', 'options' => [1 => $h->__('Yes'), 0 => $h->__('Revoked')], 'width' => '70px']);
        $this->addColumn('expires_at', ['header' => $h->__('Expires'), 'index' => 'expires_at', 'type' => 'datetime', 'width' => '140px']);
        $this->addColumn('last_used_at', ['header' => $h->__('Last Used'), 'index' => 'last_used_at', 'type' => 'datetime', 'width' => '140px']);
        $this->addColumn('last_used_ip', ['header' => $h->__('Last IP'), 'index' => 'last_used_ip', 'width' => '110px']);
        $this->addColumn('action', ['header' => $h->__('Action'), 'type' => 'action', 'getter' => 'getId', 'filter' => false, 'sortable' => false, 'width' => '80px', 'actions' => [
            ['caption' => $h->__('Revoke'), 'url' => ['base' => '*/*/revoke'], 'field' => 'id', 'confirm' => $h->__('Revoke this token? Clients using it will stop working immediately.')],
            ['caption' => $h->__('Delete'), 'url' => ['base' => '*/*/delete'], 'field' => 'id', 'confirm' => $h->__('Delete this token permanently?')],
        ]]);
        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return false;
    }
}
