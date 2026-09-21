<?php

class AiNative_Discovery_Model_Config_Source_Condition
{
    public function toOptionArray(): array
    {
        return [['value' => 'new', 'label' => 'new'], ['value' => 'refurbished', 'label' => 'refurbished'], ['value' => 'used', 'label' => 'used']];
    }
}
