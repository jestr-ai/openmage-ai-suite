<?php

class AiNative_Assistant_Block_Adminhtml_Conversation extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ainative_assistant';
        $this->_controller = 'adminhtml_conversation';
        $this->_headerText = Mage::helper('ainative_core')->__('Storefront Assistant — Transcripts');
        parent::__construct();
        $this->_removeButton('add');
    }
}
