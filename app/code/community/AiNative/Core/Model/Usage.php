<?php

/**
 * Monthly token accounting and budget enforcement.
 * @license MIT
 */
class AiNative_Core_Model_Usage extends Mage_Core_Model_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_core/usage');
    }

    public function record(string $provider, string $model, string $channel, int $in, int $out): void
    {
        try {
            $this->getResource()->increment(date('Y-m'), $provider, mb_substr($model, 0, 64), $channel, $in, $out);
        } catch (Throwable $e) {
            Mage::helper('ainative_core')->log('usage write failed: ' . $e->getMessage(), null, Zend_Log::ERR);
        }
    }

    public function getMonthTotal(?string $period = null): int
    {
        return $this->getResource()->getTotalTokens($period ?? date('Y-m'));
    }

    /**
     * @throws AiNative_Core_Exception when the monthly budget is exhausted
     */
    public function assertBudget(): void
    {
        $budget = Mage::helper('ainative_core')->getMonthlyTokenBudget();
        if ($budget > 0 && $this->getMonthTotal() >= $budget) {
            throw new AiNative_Core_Exception('The monthly AI token budget for this store has been reached. Ask the administrator to raise it under AI Suite > Security.');
        }
    }
}
