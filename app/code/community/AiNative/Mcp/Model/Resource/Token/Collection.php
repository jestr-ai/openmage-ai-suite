<?php

class AiNative_Mcp_Model_Resource_Token_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_mcp/token');
    }

    public function joinAdminUser(): self
    {
        $this->getSelect()->joinLeft(
            ['u' => $this->getTable('admin/user')],
            'u.user_id = main_table.admin_user_id',
            ['username' => 'u.username'],
        );
        return $this;
    }
}
