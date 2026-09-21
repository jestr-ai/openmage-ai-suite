<?php

class AiNative_Core_Model_Resource_Audit extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_core/audit', 'audit_id');
    }

    public function purgeOlderThan(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }
        $conn = $this->_getWriteAdapter();
        return $conn->delete($this->getMainTable(), ['created_at < ?' => date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
