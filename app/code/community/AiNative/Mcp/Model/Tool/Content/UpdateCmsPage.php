<?php

class AiNative_Mcp_Model_Tool_Content_UpdateCmsPage extends AiNative_Mcp_Model_Tool_Content_GetCmsPage
{
    public function getName(): string
    {
        return 'update_cms_page';
    }

    public function getDescription(): string
    {
        return 'Update a CMS page\'s title, content (HTML), content heading, meta keywords/description or active flag. Identify by page_id or identifier. WRITE tool — confirm with the user first; content replaces the whole page body.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'page_id' => ['type' => 'integer'],
            'identifier' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'content_heading' => ['type' => 'string'],
            'content' => ['type' => 'string'],
            'meta_keywords' => ['type' => 'string'],
            'meta_description' => ['type' => 'string'],
            'is_active' => ['type' => 'boolean'],
        ]);
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $page = Mage::getModel('cms/page');
        if (($id = $this->int($args, 'page_id')) > 0) {
            $page->load($id);
        } elseif (($ident = $this->str($args, 'identifier')) !== '') {
            $page->load($ident, 'identifier');
        }
        if (!$page->getId()) {
            $this->fail('CMS page not found.');
        }
        $changed = [];
        foreach (['title', 'content_heading', 'content', 'meta_keywords', 'meta_description'] as $f) {
            if (array_key_exists($f, $args) && (string) $page->getData($f) !== (string) $args[$f]) {
                $changed[] = $f;
                $page->setData($f, (string) $args[$f]);
            }
        }
        if (array_key_exists('is_active', $args)) {
            $page->setIsActive($this->bool($args, 'is_active') ? 1 : 0);
            $changed[] = 'is_active';
        }
        if (!$changed) {
            return ['page_id' => (int) $page->getId(), 'changed' => [], 'message' => 'Nothing to change.'];
        }
        $page->save();
        Mage::helper('ainative_core')->log(sprintf('update_cms_page %s by %s', $page->getIdentifier(), $context->getActorLabel()), $changed);
        return ['page_id' => (int) $page->getId(), 'identifier' => $page->getIdentifier(), 'changed' => $changed];
    }
}
