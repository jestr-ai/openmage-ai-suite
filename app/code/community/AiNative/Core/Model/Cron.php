<?php

class AiNative_Core_Model_Cron
{
    public function purgeAudit(): void
    {
        $days = Mage::helper('ainative_core')->getAuditRetentionDays();
        $deleted = Mage::getResourceModel('ainative_core/audit')->purgeOlderThan($days);
        if ($deleted) {
            Mage::helper('ainative_core')->log("purged {$deleted} audit rows older than {$days} days");
        }
    }
}
