<?php

class AiNative_Copilot_Adminhtml_Ainative_JobController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction(): void
    {
        $this->loadLayout()->_setActiveMenu('ainative/copilot')->_title($this->__('AI Suite'))->_title($this->__('Content Drafts'))
            ->_addContent($this->getLayout()->createBlock('ainative_copilot/adminhtml_job'))->renderLayout();
    }

    public function gridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('ainative_copilot/adminhtml_job_grid')->toHtml());
    }

    public function massGenerateAction(): void
    {
        $ids = (array) $this->getRequest()->getParam('product');
        $fields = (array) $this->getRequest()->getParam('fields', ['description']);
        $tone = (string) $this->getRequest()->getParam('tone', '');
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        $fields = array_values(array_intersect(array_map('strval', $fields), AiNative_Copilot_Model_Generator::PRODUCT_FIELDS)) ?: ['description'];
        $n = 0;
        foreach ($ids as $id) {
            $job = Mage::getModel('ainative_copilot/job');
            $job->setData([
                'type' => 'product_content',
                'entity_id' => (int) $id,
                'store_id' => $storeId,
                'fields' => implode(',', $fields),
                'options_json' => json_encode(array_filter(['tone' => $tone])),
                'status' => AiNative_Copilot_Model_Job::STATUS_PENDING,
                'created_by' => (int) Mage::getSingleton('admin/session')->getUser()->getId(),
            ])->save();
            $n++;
        }
        Mage::getSingleton('adminhtml/session')->addSuccess($this->__('%d generation job(s) queued. Drafts appear under AI Suite > Content Drafts within a few minutes (cron).', $n));
        $this->_redirect('*/*/index');
    }

    public function viewAction(): void
    {
        $job = Mage::getModel('ainative_copilot/job')->load((int) $this->getRequest()->getParam('id'));
        if (!$job->getId()) {
            $this->_redirect('*/*/');
            return;
        }
        Mage::register('ainative_job', $job);
        $this->loadLayout()->_setActiveMenu('ainative/copilot')->_title($this->__('Review Draft'))
            ->_addContent($this->getLayout()->createBlock('ainative_copilot/adminhtml_job_view'))->renderLayout();
    }

    public function applyAction(): void
    {
        $job = Mage::getModel('ainative_copilot/job')->load((int) $this->getRequest()->getParam('id'));
        try {
            if (!$job->getId()) {
                throw new AiNative_Core_Exception('Job not found.');
            }
            $overrides = $this->getRequest()->getPost('draft');
            $job->apply((int) Mage::getSingleton('admin/session')->getUser()->getId(), is_array($overrides) ? $overrides : null);
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Draft applied to %s.', $job->getEntityLabel()));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    public function rejectAction(): void
    {
        $job = Mage::getModel('ainative_copilot/job')->load((int) $this->getRequest()->getParam('id'));
        if ($job->getId()) {
            $job->reject((int) Mage::getSingleton('admin/session')->getUser()->getId());
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Draft rejected.'));
        }
        $this->_redirect('*/*/');
    }

    public function massApplyAction(): void
    {
        $n = 0;
        foreach ((array) $this->getRequest()->getParam('job') as $id) {
            $job = Mage::getModel('ainative_copilot/job')->load((int) $id);
            if ($job->getId() && $job->getStatus() === AiNative_Copilot_Model_Job::STATUS_DRAFT) {
                try {
                    $job->apply((int) Mage::getSingleton('admin/session')->getUser()->getId());
                    $n++;
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                }
            }
        }
        Mage::getSingleton('adminhtml/session')->addSuccess($this->__('%d draft(s) applied.', $n));
        $this->_redirect('*/*/');
    }

    public function massRejectAction(): void
    {
        $n = 0;
        foreach ((array) $this->getRequest()->getParam('job') as $id) {
            $job = Mage::getModel('ainative_copilot/job')->load((int) $id);
            if ($job->getId() && in_array($job->getStatus(), [AiNative_Copilot_Model_Job::STATUS_DRAFT, AiNative_Copilot_Model_Job::STATUS_PENDING, AiNative_Copilot_Model_Job::STATUS_FAILED], true)) {
                $job->reject((int) Mage::getSingleton('admin/session')->getUser()->getId());
                $n++;
            }
        }
        Mage::getSingleton('adminhtml/session')->addSuccess($this->__('%d job(s) rejected.', $n));
        $this->_redirect('*/*/');
    }

    public function runNowAction(): void
    {
        // Convenience for stores without cron: process a batch synchronously.
        Mage::getModel('ainative_copilot/cron')->processJobs();
        Mage::getSingleton('adminhtml/session')->addSuccess($this->__('Processed one batch of pending jobs.'));
        $this->_redirect('*/*/');
    }

    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('ainative/copilot');
    }
}
