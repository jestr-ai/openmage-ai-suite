<?php

class AiNative_Mcp_Model_Tool_Content_GetCmsPage extends AiNative_Core_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'get_cms_page';
    }

    public function getDescription(): string
    {
        return 'Read a CMS page by URL identifier (e.g. "about-us", "home", "privacy-policy") or list all pages when no identifier is given. Returns title, identifier, content (HTML), meta fields, is_active, store assignment.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'identifier' => ['type' => 'string'],
            'page_id' => ['type' => 'integer'],
            'store_id' => ['type' => 'integer', 'description' => 'Resolve identifier for this store view. Default: any.'],
        ]);
    }

    public function getAclResource(): ?string
    {
        return 'admin/cms/page';
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $identifier = $this->str($args, 'identifier');
        $pageId = $this->int($args, 'page_id');
        if ($identifier === '' && $pageId <= 0) {
            $list = [];
            foreach (Mage::getModel('cms/page')->getCollection()->addFieldToSelect(['page_id', 'title', 'identifier', 'is_active', 'update_time']) as $p) {
                $list[] = ['page_id' => (int) $p->getId(), 'title' => $p->getTitle(), 'identifier' => $p->getIdentifier(), 'is_active' => (bool) $p->getIsActive(), 'updated' => $p->getUpdateTime()];
            }
            return ['pages' => $list];
        }
        $page = Mage::getModel('cms/page');
        if ($pageId > 0) {
            $page->load($pageId);
        } else {
            if (isset($args['store_id'])) {
                $page->setStoreId($this->int($args, 'store_id'));
            }
            $page->load($identifier, 'identifier');
        }
        if (!$page->getId()) {
            $this->fail('CMS page not found.');
        }
        return $this->export($page);
    }

    protected function export(Mage_Cms_Model_Page $page): array
    {
        return [
            'page_id' => (int) $page->getId(),
            'title' => $page->getTitle(),
            'identifier' => $page->getIdentifier(),
            'content_heading' => $page->getContentHeading(),
            'content' => $page->getContent(),
            'meta_keywords' => $page->getMetaKeywords(),
            'meta_description' => $page->getMetaDescription(),
            'is_active' => (bool) $page->getIsActive(),
            'root_template' => $page->getRootTemplate(),
            'store_ids' => array_map('intval', (array) $page->getStoreId()),
            'creation_time' => $page->getCreationTime(),
            'update_time' => $page->getUpdateTime(),
        ];
    }
}
