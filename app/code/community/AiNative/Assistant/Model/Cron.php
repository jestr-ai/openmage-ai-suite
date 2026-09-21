<?php

class AiNative_Assistant_Model_Cron
{
    public function purge(): void
    {
        $days = Mage::helper('ainative_assistant')->getRetentionDays();
        $n = Mage::getResourceModel('ainative_copilot/conversation')->purgeOlderThan('assistant', $days);
        if ($n) {
            Mage::helper('ainative_core')->log("assistant: purged {$n} transcripts older than {$days} days");
        }
    }
}
