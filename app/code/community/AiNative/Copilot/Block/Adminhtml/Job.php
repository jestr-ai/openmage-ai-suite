<?php

class AiNative_Copilot_Block_Adminhtml_Job extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ainative_copilot';
        $this->_controller = 'adminhtml_job';
        $this->_headerText = Mage::helper('ainative_core')->__('AI Content Drafts');
        parent::__construct();
        $this->_removeButton('add');
        $this->_addButton('run_now', ['label' => Mage::helper('ainative_core')->__('Process Pending Now'), 'onclick' => "setLocation('" . $this->getUrl('*/*/runNow') . "')", 'class' => 'add']);
    }
}
