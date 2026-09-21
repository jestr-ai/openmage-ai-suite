<?php

class AiNative_Assistant_Adminhtml_Ainative_ConversationController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction(): void
    {
        $this->loadLayout()->_setActiveMenu('ainative/assistant')->_title($this->__('AI Suite'))->_title($this->__('Assistant Transcripts'))
            ->_addContent($this->getLayout()->createBlock('ainative_assistant/adminhtml_conversation_stats'))
            ->_addContent($this->getLayout()->createBlock('ainative_assistant/adminhtml_conversation'))
            ->renderLayout();
    }

    public function gridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('ainative_assistant/adminhtml_conversation_grid')->toHtml());
    }

    public function viewAction(): void
    {
        $conversation = Mage::getModel('ainative_copilot/conversation')->load((int) $this->getRequest()->getParam('id'));
        if (!$conversation->getId()) {
            $this->_redirect('*/*/');
            return;
        }
        Mage::register('ainative_conversation', $conversation);
        $this->loadLayout()->_setActiveMenu('ainative/assistant')->_title($this->__('Transcript #%d', $conversation->getId()))
            ->_addContent($this->getLayout()->createBlock('ainative_assistant/adminhtml_conversation_view'))->renderLayout();
    }

    public function deleteAction(): void
    {
        $conversation = Mage::getModel('ainative_copilot/conversation')->load((int) $this->getRequest()->getParam('id'));
        if ($conversation->getId()) {
            $conversation->delete();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Transcript deleted.'));
        }
        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $n = 0;
        foreach ((array) $this->getRequest()->getParam('conversation') as $id) {
            $c = Mage::getModel('ainative_copilot/conversation')->load((int) $id);
            if ($c->getId()) {
                $c->delete();
                $n++;
            }
        }
        Mage::getSingleton('adminhtml/session')->addSuccess($this->__('%d transcript(s) deleted.', $n));
        $this->_redirect('*/*/');
    }

    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('ainative/assistant');
    }
}
