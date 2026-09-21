<?php

class AiNative_Core_Block_Adminhtml_Audit extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ainative_core';
        $this->_controller = 'adminhtml_audit';
        $this->_headerText = Mage::helper('ainative_core')->__('AI Suite — Audit Log');
        parent::__construct();
        $this->_removeButton('add');
    }
}
