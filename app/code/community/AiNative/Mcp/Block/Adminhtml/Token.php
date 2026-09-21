<?php

class AiNative_Mcp_Block_Adminhtml_Token extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ainative_mcp';
        $this->_controller = 'adminhtml_token';
        $this->_headerText = Mage::helper('ainative_core')->__('MCP Access Tokens');
        $this->_addButtonLabel = Mage::helper('ainative_core')->__('Create Token');
        parent::__construct();
    }
}
