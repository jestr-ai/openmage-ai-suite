<?php

class AiNative_Mcp_Block_Adminhtml_Token_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $h = Mage::helper('ainative_core');
        $form = new Varien_Data_Form(['id' => 'edit_form', 'action' => $this->getUrl('*/*/save'), 'method' => 'post']);
        $form->setUseContainer(true);
        $fieldset = $form->addFieldset('token', ['legend' => $h->__('Token')]);
        $fieldset->addField('name', 'text', ['name' => 'name', 'label' => $h->__('Name'), 'required' => true, 'note' => $h->__('e.g. "Claude Desktop – Dana", "Nightly report agent"')]);

        $users = [];
        foreach (Mage::getModel('admin/user')->getCollection()->addFieldToFilter('is_active', 1)->setOrder('username', 'ASC') as $u) {
            $users[] = ['value' => $u->getId(), 'label' => $u->getUsername() . ' (' . $u->getEmail() . ')'];
        }
        $me = Mage::getSingleton('admin/session')->getUser();
        $fieldset->addField('admin_user_id', 'select', ['name' => 'admin_user_id', 'label' => $h->__('Runs as Admin User'), 'required' => true, 'values' => $users, 'value' => $me ? $me->getId() : null, 'note' => $h->__('The AI client gets exactly this user\'s role permissions. Create a dedicated, least-privilege admin user for automation.')]);
        $fieldset->addField('scope', 'select', ['name' => 'scope', 'label' => $h->__('Scope'), 'values' => [['value' => 'read', 'label' => $h->__('Read only')], ['value' => 'write', 'label' => $h->__('Read + write (also requires AI Suite > Security > Allow Write Tools)')]], 'value' => 'read']);

        $tools = [];
        foreach (Mage::getSingleton('ainative_core/tool_registry')->getTools('admin') as $name => $tool) {
            $tools[] = ['value' => $name, 'label' => $name . ($tool->isWrite() ? ' (write)' : '')];
        }
        $fieldset->addField('allowed_tools', 'multiselect', ['name' => 'allowed_tools[]', 'label' => $h->__('Restrict to Tools'), 'values' => $tools, 'note' => $h->__('Leave empty to allow every tool the admin role permits.')]);
        $fieldset->addField('expires_days', 'text', ['name' => 'expires_days', 'label' => $h->__('Expires in (days)'), 'class' => 'validate-digits', 'note' => $h->__('Empty = never.'), 'value' => 90]);

        $form->addValues(Mage::registry('ainative_token_form_data') ?: []);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
