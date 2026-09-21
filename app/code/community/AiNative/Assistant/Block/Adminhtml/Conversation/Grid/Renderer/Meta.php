<?php

class AiNative_Assistant_Block_Adminhtml_Conversation_Grid_Renderer_Meta extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $m = json_decode((string) $row->getData('meta_json'), true) ?: [];
        $out = [];
        if (!empty($m['product_cards'])) {
            $out[] = $this->__('%d product cards', (int) $m['product_cards']);
        }
        if (!empty($m['handoffs'])) {
            $out[] = '<span style="color:#c60">' . $this->__('handoff') . '</span>';
        }
        if (!empty($m['no_tool_answers'])) {
            $out[] = '<span title="' . $this->__('answers given without looking anything up') . '">' . $this->__('%d unsourced', (int) $m['no_tool_answers']) . '</span>';
        }
        return implode(' &nbsp; ', $out);
    }
}
