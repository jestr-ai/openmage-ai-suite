<?php

class AiNative_Core_Block_Adminhtml_Audit_Grid_Renderer_Tokens extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        if (!(int) $row->getData('tokens_in') && !(int) $row->getData('tokens_out')) {
            return '';
        }
        return (int) $row->getData('tokens_in') . ' / ' . (int) $row->getData('tokens_out');
    }
}
