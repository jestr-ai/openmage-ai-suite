<?php

/**
 * Stateless admin ACL check (no admin session required — used by MCP and cron).
 * @license MIT
 */
class AiNative_Core_Model_Acl
{
    private ?Zend_Acl $acl = null;

    public function isAllowed(AiNative_Core_Model_Tool_Context $context, string $resource): bool
    {
        if ($context->getActorType() === AiNative_Core_Model_Tool_Context::ACTOR_SYSTEM) {
            return true;
        }
        $user = $context->getAdminUser();
        if (!$user || !$user->getId()) {
            return false;
        }
        return $this->isUserAllowed($user, $resource);
    }

    public function isUserAllowed(Mage_Admin_Model_User $user, string $resource): bool
    {
        if (!(int) $user->getIsActive()) {
            return false;
        }
        // Reuse the live admin session ACL when this *is* the logged-in admin (Copilot). Only touch the
        // session singleton if an admin request already created it — never start sessions from API/CLI.
        $session = Mage::registry('_singleton/admin/session');
        if ($session instanceof Mage_Admin_Model_Session && $session->isLoggedIn() && (int) $session->getUser()->getId() === (int) $user->getId()) {
            return (bool) $session->isAllowed($resource);
        }
        try {
            $acl = $this->acl ??= Mage::getResourceModel('admin/acl')->loadAcl();
            $role = $user->getAclRole();
            if (!$role) {
                return false;
            }
            return $acl->isAllowed($role, $resource) || $acl->isAllowed($role, 'all');
        } catch (Throwable $e) {
            Mage::helper('ainative_core')->debug('ACL check: ' . $e->getMessage());
            return false;
        }
    }
}
