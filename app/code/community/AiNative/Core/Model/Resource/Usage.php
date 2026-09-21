<?php

class AiNative_Core_Model_Resource_Usage extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_core/usage', 'usage_id');
    }

    public function increment(string $period, string $provider, string $model, string $channel, int $in, int $out): void
    {
        $conn = $this->_getWriteAdapter();
        $conn->insertOnDuplicate(
            $this->getMainTable(),
            ['period' => $period, 'provider' => $provider, 'model' => $model, 'channel' => $channel, 'requests' => 1, 'tokens_in' => $in, 'tokens_out' => $out],
            ['requests' => new Zend_Db_Expr('requests + 1'), 'tokens_in' => new Zend_Db_Expr('tokens_in + ' . $in), 'tokens_out' => new Zend_Db_Expr('tokens_out + ' . $out)],
        );
    }

    public function getTotalTokens(string $period): int
    {
        $conn = $this->_getReadAdapter();
        $select = $conn->select()->from($this->getMainTable(), [new Zend_Db_Expr('COALESCE(SUM(tokens_in + tokens_out), 0)')])->where('period = ?', $period);
        return (int) $conn->fetchOne($select);
    }

    /** @return array<int, array<string, mixed>> */
    public function getBreakdown(string $period): array
    {
        $conn = $this->_getReadAdapter();
        return $conn->fetchAll($conn->select()->from($this->getMainTable())->where('period = ?', $period)->order('tokens_in DESC'));
    }
}
