<?php

class AiNative_Copilot_Model_Resource_Job_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_copilot/job');
    }
}
