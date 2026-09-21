<?php

/**
 * "AI reply draft" tab on the order view page.
 */
class AiNative_Copilot_Block_Adminhtml_Sales_Order_Reply extends Mage_Adminhtml_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ainative/copilot/order_reply.phtml');
    }

    public function getOrder(): Mage_Sales_Model_Order
    {
        return Mage::registry('current_order');
    }

    public function getGenerateUrl(): string
    {
        return $this->getUrl('adminhtml/ainative_copilot/orderReply');
    }

    public function getTabLabel()
    {
        return Mage::helper('ainative_core')->__('AI Reply Draft');
    }

    public function getTabTitle()
    {
        return Mage::helper('ainative_core')->__('Draft a customer e-mail with AI');
    }

    public function canShowTab()
    {
        return Mage::helper('ainative_copilot')->isEnabled() && Mage::getSingleton('admin/session')->isAllowed('ainative/copilot');
    }

    public function isHidden()
    {
        return false;
    }
}
