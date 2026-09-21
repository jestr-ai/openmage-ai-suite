<?php

class AiNative_Mcp_Block_Adminhtml_Token_Grid_Renderer_Tools extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $raw = $row->getData('allowed_tools');
        if (!$raw) {
            return $this->__('all');
        }
        $list = json_decode((string) $raw, true);
        return is_array($list) ? $this->escapeHtml(implode(', ', $list)) : $this->__('all');
    }
}
