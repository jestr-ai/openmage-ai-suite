<?php

/**
 * POST /ainative-assistant/chat/send  {message, conversation_id, form_key, page}
 * Returns JSON {conversation_id, text, cards}. Excluded from visitor logging; uses the frontend session
 * for the customer identity and a per-browser session key.
 * @license MIT
 */
class AiNative_Assistant_ChatController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        AiNative_Core_Model_Visitor_Noop::install();
        return parent::preDispatch();
    }

    public function sendAction(): void
    {
        $response = $this->getResponse()->setHeader('Content-Type', 'application/json; charset=utf-8', true)->setHeader('Cache-Control', 'no-store', true);
        $request = $this->getRequest();
        if (!$request->isPost()) {
            $response->setHttpResponseCode(405)->setBody(json_encode(['error' => 'POST only']));
            return;
        }
        $payload = json_decode((string) $request->getRawBody(), true);
        if (!is_array($payload)) {
            $payload = $request->getPost();
        }
        $formKey = (string) ($payload['form_key'] ?? '');
        if ($formKey === '' || $formKey !== Mage::getSingleton('core/session')->getFormKey()) {
            $response->setHttpResponseCode(403)->setBody(json_encode(['error' => 'Session expired. Please reload the page.']));
            return;
        }
        try {
            $session = Mage::getSingleton('customer/session');
            $customer = $session->isLoggedIn() ? $session->getCustomer() : null;
            $core = Mage::getSingleton('core/session');
            $key = (string) $core->getData('ainative_assistant_key');
            if ($key === '') {
                $key = bin2hex(random_bytes(16));
                $core->setData('ainative_assistant_key', $key);
            }
            $result = Mage::getModel('ainative_assistant/chat')->send(
                (string) ($payload['message'] ?? ''),
                $key,
                isset($payload['conversation_id']) ? (int) $payload['conversation_id'] : null,
                (int) Mage::app()->getStore()->getId(),
                $customer,
                mb_substr((string) ($payload['page'] ?? ''), 0, 300),
            );
            $response->setBody(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (AiNative_Core_Exception_Provider $e) {
            // The shopper must never see provider internals (status codes, keys, model names).
            Mage::helper('ainative_core')->log('assistant provider failure: ' . $e->getMessage(), null, Zend_Log::ERR);
            $response->setHttpResponseCode($e->isTransient() ? 503 : 500)->setBody(json_encode([
                'error' => $e->isTransient()
                    ? Mage::helper('ainative_core')->__('I am a little busy right now. Please try again in a moment.')
                    : Mage::helper('ainative_core')->__('The assistant is unavailable right now. Please contact the store directly.'),
                'retryable' => $e->isTransient(),
            ]));
        } catch (AiNative_Core_Exception $e) {
            $response->setHttpResponseCode(400)->setBody(json_encode(['error' => $e->getMessage()]));
        } catch (Throwable $e) {
            Mage::helper('ainative_core')->log('assistant error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()], Zend_Log::ERR);
            $response->setHttpResponseCode(500)->setBody(json_encode(['error' => Mage::helper('ainative_core')->__('Sorry, something went wrong. Please try again in a moment.')]));
        }
    }
}
