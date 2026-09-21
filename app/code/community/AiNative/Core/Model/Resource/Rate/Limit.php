<?php

class AiNative_Core_Model_Resource_Rate_Limit extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_core/rate_limit', 'bucket');
        $this->_isPkAutoIncrement = false;
    }

    public function increment(string $bucket): int
    {
        $conn = $this->_getWriteAdapter();
        $conn->insertOnDuplicate(
            $this->getMainTable(),
            ['bucket' => $bucket, 'hits' => 1, 'expires_at' => date('Y-m-d H:i:s', time() + 120)],
            ['hits' => new Zend_Db_Expr('hits + 1')],
        );
        if (random_int(1, 50) === 1) {
            $conn->delete($this->getMainTable(), ['expires_at < ?' => date('Y-m-d H:i:s')]);
        }
        return (int) $conn->fetchOne($conn->select()->from($this->getMainTable(), 'hits')->where('bucket = ?', $bucket));
    }
}
