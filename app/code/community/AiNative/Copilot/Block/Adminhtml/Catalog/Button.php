<?php

/**
 * Injects the "Generate with AI" script on product/category edit pages.
 */
class AiNative_Copilot_Block_Adminhtml_Catalog_Button extends Mage_Adminhtml_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ainative/copilot/catalog_button.phtml');
    }

    protected function _toHtml()
    {
        if (!Mage::helper('ainative_copilot')->isEnabled() || !Mage::getSingleton('admin/session')->isAllowed('ainative/copilot')) {
            return '';
        }
        return parent::_toHtml();
    }

    public function getGenerateUrl(): string
    {
        return $this->getUrl('adminhtml/ainative_copilot/generate');
    }

    public function getSearchUrl(): string
    {
        return $this->getUrl('adminhtml/ainative_copilot/searchProducts');
    }

    public function getEntityType(): string
    {
        return (string) $this->getData('entity_type') ?: 'product';
    }

    public function getEntityId(): int
    {
        if ($this->getEntityType() === 'category') {
            $c = Mage::registry('current_category');
            return $c ? (int) $c->getId() : 0;
        }
        $p = Mage::registry('current_product') ?: Mage::registry('product');
        return $p ? (int) $p->getId() : 0;
    }

    public function getStoreId(): int
    {
        return (int) $this->getRequest()->getParam('store', 0);
    }

    public function getFields(): array
    {
        return $this->getEntityType() === 'category' ? AiNative_Copilot_Model_Generator::CATEGORY_FIELDS : AiNative_Copilot_Model_Generator::PRODUCT_FIELDS;
    }

    public function getTones(): array
    {
        return AiNative_Copilot_Model_Config_Source_Tone::TONES;
    }

    public function getDefaultTone(): string
    {
        return Mage::helper('ainative_copilot')->getTone($this->getStoreId());
    }
}
