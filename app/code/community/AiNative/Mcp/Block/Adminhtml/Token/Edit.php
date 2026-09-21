<?php

class AiNative_Mcp_Block_Adminhtml_Token_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ainative_mcp';
        $this->_controller = 'adminhtml_token';
        $this->_mode = 'edit';
        parent::__construct();
        $this->_updateButton('save', 'label', Mage::helper('ainative_core')->__('Create Token'));
        $this->_removeButton('delete');
        $this->_removeButton('reset');
    }

    public function getHeaderText()
    {
        return Mage::helper('ainative_core')->__('Create MCP Access Token');
    }

    public function getSaveUrl()
    {
        return $this->getUrl('*/*/save');
    }
}
