<?php

class AiNative_Mcp_Block_Adminhtml_Token_Grid_Renderer_Prefix extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        return '<code>' . $this->escapeHtml((string) $row->getData('token_prefix')) . '…</code>';
    }
}
