<?php

class AiNative_Mcp_Adminhtml_Ainative_TokenController extends Mage_Adminhtml_Controller_Action
{
    protected function _initAction(): self
    {
        $this->loadLayout()->_setActiveMenu('ainative/tokens')->_title($this->__('AI Suite'))->_title($this->__('MCP Access Tokens'));
        return $this;
    }

    public function indexAction(): void
    {
        $this->_initAction();
        $h = Mage::helper('ainative_mcp');
        $info = $this->getLayout()->createBlock('core/text')->setText(
            '<div class="notice-msg" style="margin-bottom:10px"><b>' . $this->__('MCP endpoint') . ':</b> <code>' . Mage::helper('core')->escapeHtml($h->getEndpointUrl()) . '</code>'
            . ($h->isEnabled() ? '' : ' — <span style="color:#d40707">' . $this->__('endpoint is DISABLED (AI Suite > MCP Server)') . '</span>')
            . '<br/>' . $this->__('Connect with header <code>Authorization: Bearer &lt;token&gt;</code>. Claude Code: <code>claude mcp add --transport http openmage %s --header "Authorization: Bearer &lt;token&gt;"</code>', Mage::helper('core')->escapeHtml($h->getEndpointUrl()))
            . '</div>',
        );
        $this->_addContent($info)->_addContent($this->getLayout()->createBlock('ainative_mcp/adminhtml_token'))->renderLayout();
    }

    public function gridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('ainative_mcp/adminhtml_token_grid')->toHtml());
    }

    public function newAction(): void
    {
        $this->_initAction()->_addContent($this->getLayout()->createBlock('ainative_mcp/adminhtml_token_edit'))->renderLayout();
    }

    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data || !$this->_validateFormKey()) {
            $this->_redirect('*/*/');
            return;
        }
        try {
            $name = trim((string) ($data['name'] ?? ''));
            $userId = (int) ($data['admin_user_id'] ?? 0);
            if ($name === '' || $userId <= 0) {
                throw new AiNative_Core_Exception($this->__('Name and admin user are required.'));
            }
            $user = Mage::getModel('admin/user')->load($userId);
            if (!$user->getId()) {
                throw new AiNative_Core_Exception($this->__('Admin user not found.'));
            }
            $me = Mage::getSingleton('admin/session')->getUser();
            if ((int) $user->getId() !== (int) $me->getId() && !Mage::getSingleton('admin/session')->isAllowed('admin/system/acl/users')) {
                throw new AiNative_Core_Exception($this->__('You may only create tokens for yourself unless you can manage admin users.'));
            }
            $tools = array_values(array_filter((array) ($data['allowed_tools'] ?? []), 'is_string'));
            $days = (int) ($data['expires_days'] ?? 0);
            $expires = $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
            $secret = Mage::getModel('ainative_mcp/token')->issue($name, $userId, (string) ($data['scope'] ?? 'read'), $tools ?: null, $expires);
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Token created. Copy it now — it will not be shown again:'));
            Mage::getSingleton('adminhtml/session')->addNotice('<code style="font-size:14px;user-select:all">' . Mage::helper('core')->escapeHtml($secret) . '</code>');
            Mage::helper('ainative_core')->log(sprintf('MCP token "%s" issued for user #%d by %s', $name, $userId, $me->getUsername()));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            Mage::register('ainative_token_form_data', $data);
            $this->_redirect('*/*/new');
            return;
        }
        $this->_redirect('*/*/');
    }

    public function revokeAction(): void
    {
        $token = Mage::getModel('ainative_mcp/token')->load((int) $this->getRequest()->getParam('id'));
        if ($token->getId()) {
            $token->setData('is_active', 0)->save();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Token revoked.'));
        }
        $this->_redirect('*/*/');
    }

    public function deleteAction(): void
    {
        $token = Mage::getModel('ainative_mcp/token')->load((int) $this->getRequest()->getParam('id'));
        if ($token->getId()) {
            $token->delete();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Token deleted.'));
        }
        $this->_redirect('*/*/');
    }

    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('ainative/tokens');
    }
}
