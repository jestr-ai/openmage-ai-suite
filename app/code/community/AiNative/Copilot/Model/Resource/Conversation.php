<?php

class AiNative_Copilot_Model_Resource_Conversation extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_copilot/conversation', 'conversation_id');
    }

    public function purgeOlderThan(string $channel, int $days): int
    {
        if ($days <= 0) {
            return 0;
        }
        return $this->_getWriteAdapter()->delete($this->getMainTable(), ['channel = ?' => $channel, 'COALESCE(updated_at, created_at) < ?' => date('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
