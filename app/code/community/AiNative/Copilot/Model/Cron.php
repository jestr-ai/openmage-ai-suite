<?php

class AiNative_Copilot_Model_Cron
{
    public function processJobs(): void
    {
        if (!Mage::helper('ainative_copilot')->isEnabled()) {
            return;
        }
        $ids = Mage::getResourceModel('ainative_copilot/job')->claimPending(Mage::helper('ainative_copilot')->getJobsPerRun());
        foreach ($ids as $id) {
            $job = Mage::getModel('ainative_copilot/job')->load($id);
            if ($job->getId()) {
                $job->process();
            }
        }
    }
}
