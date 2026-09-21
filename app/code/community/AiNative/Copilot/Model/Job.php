<?php

/**
 * Bulk generation job: queued by a grid mass action, processed by cron, reviewed as a draft, then applied.
 * @method string getType()
 * @method int getEntityId()
 * @method int getStoreId()
 * @method string getStatus()
 * @method string getFields()
 * @license MIT
 */
class AiNative_Copilot_Model_Job extends Mage_Core_Model_Abstract
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    protected function _construct(): void
    {
        $this->_init('ainative_copilot/job');
    }

    public function getDraft(): array
    {
        $d = json_decode((string) $this->getData('draft_json'), true);
        return is_array($d) ? $d : [];
    }

    public function getOptions(): array
    {
        $o = json_decode((string) $this->getData('options_json'), true);
        return is_array($o) ? $o : [];
    }

    public function process(): void
    {
        $this->setData('status', self::STATUS_RUNNING)->setData('updated_at', date('Y-m-d H:i:s'))->save();
        try {
            $context = AiNative_Core_Model_Tool_Context::forSystem('copilot', (int) $this->getStoreId());
            $generator = Mage::getModel('ainative_copilot/generator');
            $fields = array_filter(array_map('trim', explode(',', (string) $this->getFields())));
            if ($this->getType() === 'category_content') {
                $category = Mage::getModel('catalog/category')->setStoreId((int) $this->getStoreId())->load((int) $this->getEntityId());
                if (!$category->getId()) {
                    throw new AiNative_Core_Exception('Category no longer exists.');
                }
                $result = $generator->forCategory($category, $fields, $this->getOptions(), $context);
            } else {
                $product = Mage::getModel('catalog/product')->setStoreId((int) $this->getStoreId())->load((int) $this->getEntityId());
                if (!$product->getId()) {
                    throw new AiNative_Core_Exception('Product no longer exists.');
                }
                $result = $generator->forProduct($product, $fields, $this->getOptions(), $context);
            }
            $this->setData('draft_json', json_encode($result['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                ->setData('tokens_in', $result['tokens_in'])
                ->setData('tokens_out', $result['tokens_out'])
                ->setData('status', self::STATUS_DRAFT)
                ->setData('error', null);
        } catch (Throwable $e) {
            $this->setData('status', self::STATUS_FAILED)->setData('error', mb_substr($e->getMessage(), 0, 2000));
        }
        $this->setData('updated_at', date('Y-m-d H:i:s'))->save();
    }

    /**
     * Write the draft into the entity. Only fields present in the draft are touched.
     */
    public function apply(int $adminId, ?array $overrides = null): void
    {
        if ($this->getStatus() !== self::STATUS_DRAFT) {
            throw new AiNative_Core_Exception('Only drafts can be applied.');
        }
        $draft = $overrides ?? $this->getDraft();
        if ($this->getType() === 'category_content') {
            $entity = Mage::getModel('catalog/category')->setStoreId((int) $this->getStoreId())->load((int) $this->getEntityId());
            $allowed = AiNative_Copilot_Model_Generator::CATEGORY_FIELDS;
        } else {
            $entity = Mage::getModel('catalog/product')->setStoreId((int) $this->getStoreId())->load((int) $this->getEntityId());
            $allowed = AiNative_Copilot_Model_Generator::PRODUCT_FIELDS;
        }
        if (!$entity->getId()) {
            throw new AiNative_Core_Exception('Entity no longer exists.');
        }
        foreach ($draft as $field => $value) {
            if (in_array($field, $allowed, true) && is_string($value) && $value !== '') {
                $entity->setData($field, $value);
            }
        }
        $entity->save();
        $this->setData('status', self::STATUS_APPLIED)->setData('reviewed_by', $adminId)->setData('updated_at', date('Y-m-d H:i:s'))->save();
    }

    public function reject(int $adminId): void
    {
        $this->setData('status', self::STATUS_REJECTED)->setData('reviewed_by', $adminId)->setData('updated_at', date('Y-m-d H:i:s'))->save();
    }

    public function getEntityLabel(): string
    {
        if ($this->getType() === 'category_content') {
            $c = Mage::getModel('catalog/category')->load((int) $this->getEntityId());
            return $c->getId() ? $c->getName() : '#' . $this->getEntityId();
        }
        $p = Mage::getModel('catalog/product')->load((int) $this->getEntityId());
        return $p->getId() ? $p->getSku() . ' — ' . $p->getName() : '#' . $this->getEntityId();
    }
}
