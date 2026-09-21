<?php

class AiNative_Core_Block_Adminhtml_Audit_Grid_Renderer_Truncate extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $value = (string) $row->getData($this->getColumn()->getIndex());
        $short = mb_strlen($value) > 160 ? mb_substr($value, 0, 160) . '…' : $value;
        return '<span title="' . $this->escapeHtml(mb_substr($value, 0, 2000)) . '" style="font-family:monospace;font-size:11px">' . $this->escapeHtml($short) . '</span>';
    }
}
