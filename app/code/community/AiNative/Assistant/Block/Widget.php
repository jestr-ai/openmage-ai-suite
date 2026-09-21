<?php

class AiNative_Assistant_Block_Widget extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ainative/assistant/widget.phtml');
    }

    protected function _toHtml()
    {
        if (!Mage::helper('ainative_assistant')->isEnabled()) {
            return '';
        }
        return parent::_toHtml();
    }

    public function getConfigJson(): string
    {
        $h = Mage::helper('ainative_assistant');
        $storeId = (int) Mage::app()->getStore()->getId();
        return json_encode([
            'url' => $h->getChatUrl(),
            'formKey' => Mage::getSingleton('core/session')->getFormKey(),
            'name' => $h->cfg('name', $storeId) ?: 'Shop Assistant',
            'greeting' => $h->cfg('greeting', $storeId),
            'suggestions' => $h->getSuggestions($storeId),
            'position' => $h->cfg('position', $storeId) === 'left' ? 'left' : 'right',
            'accent' => preg_match('/^#[0-9a-f]{3,8}$/i', $h->cfg('accent_color', $storeId)) ? $h->cfg('accent_color', $storeId) : '#1f6feb',
            'labels' => [
                'placeholder' => $this->__('Type your question…'),
                'send' => $this->__('Send'),
                'open' => $this->__('Chat with us'),
                'close' => $this->__('Close chat'),
                'thinking' => $this->__('Thinking…'),
                'addToCart' => $this->__('Add to cart'),
                'view' => $this->__('View'),
                'contact' => $this->__('Contact the store'),
                'track' => $this->__('Track'),
                'newChat' => $this->__('New chat'),
                'disclaimer' => $this->__('AI assistant — answers may contain mistakes; check the product page before ordering.'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    public function getNonceAttr(): string
    {
        try {
            $csp = Mage::helper('csp');
            if ($csp->isEnabled(Mage_Core_Model_App_Area::AREA_FRONTEND) && method_exists($csp, 'getNonce')) {
                return ' nonce="' . $this->escapeHtml($csp->getNonce()) . '"';
            }
        } catch (Throwable) {
        }
        return '';
    }
}
