<?php

class AiNative_Core_Model_Config_Source_Provider
{
    public function toOptionArray(): array
    {
        $options = [
            ['value' => 'anthropic', 'label' => 'Anthropic (Claude)'],
            ['value' => 'openai', 'label' => 'OpenAI / OpenAI-compatible'],
            ['value' => 'gemini', 'label' => 'Google Gemini'],
        ];
        if (Mage::getIsDeveloperMode()) {
            $options[] = ['value' => 'mock', 'label' => 'Mock (offline, for testing)'];
        }
        return $options;
    }
}
