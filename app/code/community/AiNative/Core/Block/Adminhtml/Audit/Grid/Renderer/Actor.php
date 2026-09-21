<?php

class AiNative_Core_Block_Adminhtml_Audit_Grid_Renderer_Actor extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        return $this->escapeHtml($row->getData('actor_type') . ' · ' . $row->getData('actor_label'));
    }
}
