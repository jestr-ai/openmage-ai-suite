<?php

class AiNative_Assistant_Model_Config_Source_Position
{
    public function toOptionArray(): array
    {
        return [['value' => 'right', 'label' => 'Bottom right'], ['value' => 'left', 'label' => 'Bottom left']];
    }
}
