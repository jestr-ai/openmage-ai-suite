<?php

class AiNative_Assistant_Block_Adminhtml_Conversation_View extends Mage_Adminhtml_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ainative/assistant/view.phtml');
    }

    public function getConversation(): AiNative_Copilot_Model_Conversation
    {
        return Mage::registry('ainative_conversation');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['id' => $this->getConversation()->getId()]);
    }
}
