<?php

class AiNative_Copilot_Block_Adminhtml_Job_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('ainativeJobGrid');
        $this->setDefaultSort('job_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $this->setCollection(Mage::getResourceModel('ainative_copilot/job_collection'));
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $h = Mage::helper('ainative_core');
        $this->addColumn('job_id', ['header' => 'ID', 'index' => 'job_id', 'width' => '50px']);
        $this->addColumn('created_at', ['header' => $h->__('Queued'), 'index' => 'created_at', 'type' => 'datetime', 'width' => '140px']);
        $this->addColumn('type', ['header' => $h->__('Type'), 'index' => 'type', 'type' => 'options', 'options' => ['product_content' => $h->__('Product'), 'category_content' => $h->__('Category')], 'width' => '90px']);
        $this->addColumn('entity_id', ['header' => $h->__('Entity ID'), 'index' => 'entity_id', 'width' => '70px']);
        $this->addColumn('store_id', ['header' => $h->__('Store'), 'index' => 'store_id', 'type' => 'store', 'store_view' => true, 'width' => '120px']);
        $this->addColumn('fields', ['header' => $h->__('Fields'), 'index' => 'fields']);
        $this->addColumn('status', ['header' => $h->__('Status'), 'index' => 'status', 'type' => 'options', 'options' => [
            'pending' => $h->__('Pending'), 'running' => $h->__('Running'), 'draft' => $h->__('Draft — review'), 'applied' => $h->__('Applied'), 'rejected' => $h->__('Rejected'), 'failed' => $h->__('Failed'),
        ], 'width' => '110px']);
        $this->addColumn('error', ['header' => $h->__('Error'), 'index' => 'error', 'renderer' => 'ainative_core/adminhtml_audit_grid_renderer_truncate']);
        $this->addColumn('tokens', ['header' => $h->__('Tokens in/out'), 'index' => 'tokens_in', 'renderer' => 'ainative_core/adminhtml_audit_grid_renderer_tokens', 'filter' => false, 'width' => '90px']);
        $this->addColumn('action', ['header' => $h->__('Action'), 'type' => 'action', 'getter' => 'getId', 'filter' => false, 'sortable' => false, 'width' => '80px', 'actions' => [
            ['caption' => $h->__('Review'), 'url' => ['base' => '*/*/view'], 'field' => 'id'],
        ]]);
        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $h = Mage::helper('ainative_core');
        $this->setMassactionIdField('job_id');
        $this->getMassactionBlock()->setFormFieldName('job');
        $this->getMassactionBlock()->addItem('apply', ['label' => $h->__('Apply drafts'), 'url' => $this->getUrl('*/*/massApply'), 'confirm' => $h->__('Write the selected drafts into the products/categories?')]);
        $this->getMassactionBlock()->addItem('reject', ['label' => $h->__('Reject'), 'url' => $this->getUrl('*/*/massReject')]);
        return $this;
    }

    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/view', ['id' => $row->getId()]);
    }
}
