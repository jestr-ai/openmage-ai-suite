<?php

class AiNative_Copilot_Block_Adminhtml_Job_View extends Mage_Adminhtml_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ainative/copilot/job_view.phtml');
    }

    public function getJob(): AiNative_Copilot_Model_Job
    {
        return Mage::registry('ainative_job');
    }

    public function getCurrentValue(string $field): string
    {
        $job = $this->getJob();
        $entity = $job->getType() === 'category_content'
            ? Mage::getModel('catalog/category')->setStoreId((int) $job->getStoreId())->load((int) $job->getEntityId())
            : Mage::getModel('catalog/product')->setStoreId((int) $job->getStoreId())->load((int) $job->getEntityId());
        return (string) $entity->getData($field);
    }

    public function getApplyUrl(): string
    {
        return $this->getUrl('*/*/apply', ['id' => $this->getJob()->getId()]);
    }

    public function getRejectUrl(): string
    {
        return $this->getUrl('*/*/reject', ['id' => $this->getJob()->getId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }
}
