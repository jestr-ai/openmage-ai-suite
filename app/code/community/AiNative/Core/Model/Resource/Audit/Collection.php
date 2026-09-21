<?php

class AiNative_Core_Model_Resource_Audit_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_core/audit');
    }
}
