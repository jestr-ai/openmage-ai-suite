<?php

class AiNative_Mcp_Model_Resource_Token extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_mcp/token', 'token_id');
    }

    public function touch(int $id, string $ip): void
    {
        $this->_getWriteAdapter()->update($this->getMainTable(), ['last_used_at' => date('Y-m-d H:i:s'), 'last_used_ip' => mb_substr($ip, 0, 45)], ['token_id = ?' => $id]);
    }
}
