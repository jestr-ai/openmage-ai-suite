<?php

class AiNative_Copilot_Model_Config_Source_Tone
{
    public const TONES = [
        'professional' => 'Professional and clear',
        'friendly' => 'Friendly and conversational',
        'luxury' => 'Premium / luxury',
        'playful' => 'Playful and energetic',
        'technical' => 'Technical and precise',
        'minimal' => 'Minimal, no fluff',
    ];

    public function toOptionArray(): array
    {
        $out = [];
        foreach (self::TONES as $k => $v) {
            $out[] = ['value' => $k, 'label' => $v];
        }
        return $out;
    }
}
