<?php

class AiNative_Assistant_Block_Adminhtml_Conversation_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('ainativeConversationGrid');
        $this->setDefaultSort('updated_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('ainative_copilot/conversation_collection')->addFieldToFilter('channel', 'assistant');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $h = Mage::helper('ainative_core');
        $this->addColumn('conversation_id', ['header' => 'ID', 'index' => 'conversation_id', 'width' => '50px']);
        $this->addColumn('updated_at', ['header' => $h->__('Last Message'), 'index' => 'updated_at', 'type' => 'datetime', 'width' => '140px']);
        $this->addColumn('store_id', ['header' => $h->__('Store'), 'index' => 'store_id', 'type' => 'store', 'store_view' => true, 'width' => '120px']);
        $this->addColumn('actor_type', ['header' => $h->__('Visitor'), 'index' => 'actor_type', 'type' => 'options', 'options' => ['guest' => $h->__('Guest'), 'customer' => $h->__('Customer')], 'width' => '80px']);
        $this->addColumn('actor_id', ['header' => $h->__('Customer ID'), 'index' => 'actor_id', 'width' => '80px']);
        $this->addColumn('title', ['header' => $h->__('First Question'), 'index' => 'title']);
        $this->addColumn('messages_count', ['header' => $h->__('Msgs'), 'index' => 'messages_count', 'type' => 'number', 'width' => '50px']);
        $this->addColumn('meta_json', ['header' => $h->__('Signals'), 'index' => 'meta_json', 'renderer' => 'ainative_assistant/adminhtml_conversation_grid_renderer_meta', 'filter' => false, 'sortable' => false]);
        $this->addColumn('tokens', ['header' => $h->__('Tokens in/out'), 'index' => 'tokens_in', 'renderer' => 'ainative_core/adminhtml_audit_grid_renderer_tokens', 'filter' => false, 'width' => '90px']);
        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('conversation_id');
        $this->getMassactionBlock()->setFormFieldName('conversation');
        $this->getMassactionBlock()->addItem('delete', ['label' => Mage::helper('ainative_core')->__('Delete'), 'url' => $this->getUrl('*/*/massDelete'), 'confirm' => Mage::helper('ainative_core')->__('Delete selected transcripts?')]);
        return $this;
    }

    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/view', ['id' => $row->getId()]);
    }
}
