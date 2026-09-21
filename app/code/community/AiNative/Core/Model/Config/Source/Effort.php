<?php

class AiNative_Core_Model_Config_Source_Effort
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => Mage::helper('ainative_core')->__('Model default')],
            ['value' => 'low', 'label' => 'low'],
            ['value' => 'medium', 'label' => 'medium'],
            ['value' => 'high', 'label' => 'high'],
        ];
    }
}
