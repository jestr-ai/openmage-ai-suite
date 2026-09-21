<?php

/**
 * Routes /llms.txt to the discovery controller (frontNames cannot contain a dot).
 */
class AiNative_Discovery_Controller_Router extends Mage_Core_Controller_Varien_Router_Abstract
{
    public function initControllerRouters(Varien_Event_Observer $observer): void
    {
        $observer->getEvent()->getFront()->addRouter('ainative_llms', $this);
    }

    public function match(Zend_Controller_Request_Http $request)
    {
        $path = trim($request->getPathInfo(), '/');
        if ($path !== 'llms.txt' && $path !== 'llms-full.txt') {
            return false;
        }
        if (!Mage::helper('ainative_discovery')->isEnabled() || !Mage::helper('ainative_discovery')->flag('llms_txt')) {
            return false;
        }
        $request->setModuleName('ainative-discovery')->setControllerName('index')->setActionName('llms')->setParam('full', $path === 'llms-full.txt' ? 1 : 0);
        $request->setAlias(Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS, $path);
        return true;
    }
}
