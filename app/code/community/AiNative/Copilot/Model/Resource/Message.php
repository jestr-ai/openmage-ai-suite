<?php

class AiNative_Copilot_Model_Resource_Message extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_copilot/message', 'message_id');
    }
}
