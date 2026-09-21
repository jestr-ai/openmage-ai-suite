<?php

class AiNative_Core_Block_Adminhtml_Audit_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('ainativeAuditGrid');
        $this->setDefaultSort('audit_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $this->setCollection(Mage::getResourceModel('ainative_core/audit_collection'));
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $h = Mage::helper('ainative_core');
        $this->addColumn('audit_id', ['header' => 'ID', 'index' => 'audit_id', 'width' => '60px']);
        $this->addColumn('created_at', ['header' => $h->__('When'), 'index' => 'created_at', 'type' => 'datetime', 'width' => '150px']);
        $this->addColumn('channel', ['header' => $h->__('Channel'), 'index' => 'channel', 'type' => 'options', 'options' => ['mcp' => 'MCP', 'copilot' => 'Copilot', 'assistant' => 'Assistant', 'cron' => 'Cron'], 'width' => '80px']);
        $this->addColumn('actor', ['header' => $h->__('Actor'), 'index' => 'actor_label', 'renderer' => 'ainative_core/adminhtml_audit_grid_renderer_actor', 'filter_index' => 'actor_label']);
        $this->addColumn('kind', ['header' => $h->__('Kind'), 'index' => 'kind', 'type' => 'options', 'options' => ['tool' => 'tool', 'llm' => 'llm', 'denied' => 'denied', 'error' => 'error'], 'width' => '70px']);
        $this->addColumn('name', ['header' => $h->__('Tool / Model'), 'index' => 'name']);
        $this->addColumn('is_write', ['header' => $h->__('Write'), 'index' => 'is_write', 'type' => 'options', 'options' => [0 => $h->__('No'), 1 => $h->__('Yes')], 'width' => '60px']);
        $this->addColumn('args_json', ['header' => $h->__('Arguments'), 'index' => 'args_json', 'renderer' => 'ainative_core/adminhtml_audit_grid_renderer_truncate']);
        $this->addColumn('result_summary', ['header' => $h->__('Result'), 'index' => 'result_summary', 'renderer' => 'ainative_core/adminhtml_audit_grid_renderer_truncate']);
        $this->addColumn('tokens', ['header' => $h->__('Tokens in/out'), 'index' => 'tokens_in', 'renderer' => 'ainative_core/adminhtml_audit_grid_renderer_tokens', 'width' => '90px', 'filter' => false]);
        $this->addColumn('duration_ms', ['header' => $h->__('ms'), 'index' => 'duration_ms', 'type' => 'number', 'width' => '50px']);
        $this->addExportType('*/*/exportCsv', $h->__('CSV'));
        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return false;
    }
}
