<?php

class AiNative_Copilot_Block_Adminhtml_Ask extends Mage_Adminhtml_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ainative/copilot/ask.phtml');
    }

    public function getSendUrl(): string
    {
        return $this->getUrl('*/*/send');
    }

    public function getHistoryUrl(): string
    {
        return $this->getUrl('*/*/history');
    }

    public function getLoadUrl(): string
    {
        return $this->getUrl('*/*/load');
    }

    public function getProviderLabel(): string
    {
        $h = Mage::helper('ainative_core');
        try {
            $p = $h->getProvider();
            return $p->getCode() . ' / ' . $p->getModel() . ($p->isConfigured() ? '' : ' — ' . $this->__('NOT CONFIGURED'));
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function isWriteAllowed(): bool
    {
        return Mage::helper('ainative_core')->isWriteAllowed();
    }

    /** @return string[] */
    public function getSuggestions(): array
    {
        return [
            $this->__('What were sales yesterday compared to the same day last week?'),
            $this->__('Which products are low on stock and sold in the last 30 days?'),
            $this->__('Show me pending orders older than 3 days.'),
            $this->__('Which cart price rules are active right now?'),
            $this->__('Find customers who ordered more than 3 times.'),
        ];
    }
}
