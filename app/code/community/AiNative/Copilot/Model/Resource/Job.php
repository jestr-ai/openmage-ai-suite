<?php

class AiNative_Copilot_Model_Resource_Job extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_copilot/job', 'job_id');
    }

    /** Atomically claim up to $limit pending job ids. */
    public function claimPending(int $limit): array
    {
        $conn = $this->_getWriteAdapter();
        $ids = $conn->fetchCol($conn->select()->from($this->getMainTable(), 'job_id')->where('status = ?', AiNative_Copilot_Model_Job::STATUS_PENDING)->order('job_id ASC')->limit($limit));
        if (!$ids) {
            return [];
        }
        $conn->update($this->getMainTable(), ['status' => AiNative_Copilot_Model_Job::STATUS_RUNNING, 'updated_at' => date('Y-m-d H:i:s')], ['job_id IN (?)' => $ids, 'status = ?' => AiNative_Copilot_Model_Job::STATUS_PENDING]);
        return array_map('intval', $ids);
    }
}
