<?php

class AiNative_Core_Adminhtml_Ainative_AuditController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction(): void
    {
        $this->loadLayout()
            ->_setActiveMenu('ainative/audit')
            ->_title($this->__('AI Suite'))->_title($this->__('Audit Log'))
            ->_addContent($this->getLayout()->createBlock('ainative_core/adminhtml_audit'))
            ->renderLayout();
    }

    public function gridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('ainative_core/adminhtml_audit_grid')->toHtml());
    }

    public function exportCsvAction(): void
    {
        $grid = $this->getLayout()->createBlock('ainative_core/adminhtml_audit_grid');
        $this->_prepareDownloadResponse('ainative-audit.csv', $grid->getCsvFile());
    }

    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('ainative/audit');
    }
}
