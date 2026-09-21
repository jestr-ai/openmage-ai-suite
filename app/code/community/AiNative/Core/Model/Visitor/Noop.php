<?php

/**
 * Stand-in for Mage_Log_Model_Visitor on API endpoints. Mage_Log's constructor starts a PHP session
 * (and sets a cookie) on every frontend request; registering this no-op as the singleton before
 * preDispatch keeps API routes cookie- and session-free.
 * @license MIT
 */
class AiNative_Core_Model_Visitor_Noop extends Mage_Log_Model_Visitor
{
    public function __construct()
    {
        // intentionally no parent::__construct(): no session, no user-agent parsing
    }

    public static function install(): void
    {
        $key = '_singleton/log/visitor';
        if (!Mage::registry($key)) {
            Mage::register($key, new self());
        }
    }

    public function initByRequest($observer)
    {
        return $this;
    }

    public function saveByRequest($observer)
    {
        return $this;
    }

    public function bindCustomerLogin($observer)
    {
        return $this;
    }

    public function bindCustomerLogout($observer)
    {
        return $this;
    }

    public function bindQuoteCreate($observer)
    {
        return $this;
    }

    public function bindQuoteDestroy($observer)
    {
        return $this;
    }

    public function addCustomerData($observer)
    {
        return $this;
    }
}
