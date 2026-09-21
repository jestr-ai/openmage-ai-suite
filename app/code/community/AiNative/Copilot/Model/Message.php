<?php

class AiNative_Copilot_Model_Message extends Mage_Core_Model_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_copilot/message');
    }

    public function getCards(): array
    {
        $c = json_decode((string) $this->getData('cards_json'), true);
        return is_array($c) ? $c : [];
    }
}
