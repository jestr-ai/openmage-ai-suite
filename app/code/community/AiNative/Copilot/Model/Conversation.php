<?php

/**
 * Persisted conversation (admin Ask or storefront Assistant).
 * @method string getChannel()
 * @method int getStoreId()
 * @license MIT
 */
class AiNative_Copilot_Model_Conversation extends Mage_Core_Model_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_copilot/conversation');
    }

    public function getState(): AiNative_Core_Model_Conversation
    {
        $raw = json_decode((string) $this->getData('state_json'), true);
        return is_array($raw) ? AiNative_Core_Model_Conversation::fromArray($raw) : AiNative_Core_Model_Conversation::create();
    }

    public function setState(AiNative_Core_Model_Conversation $state): self
    {
        $this->setData('state_json', json_encode($state->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $this;
    }

    public function getMeta(): array
    {
        $m = json_decode((string) $this->getData('meta_json'), true);
        return is_array($m) ? $m : [];
    }

    public function bumpMeta(string $key, int $by = 1): self
    {
        $m = $this->getMeta();
        $m[$key] = (int) ($m[$key] ?? 0) + $by;
        $this->setData('meta_json', json_encode($m));
        return $this;
    }

    public function addMessage(string $role, ?string $content, array $cards = [], int $toolCalls = 0, int $in = 0, int $out = 0): AiNative_Copilot_Model_Message
    {
        $msg = Mage::getModel('ainative_copilot/message');
        $msg->setData([
            'conversation_id' => (int) $this->getId(),
            'role' => $role,
            'content' => $content,
            'cards_json' => $cards ? json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'tool_calls' => $toolCalls,
            'tokens_in' => $in,
            'tokens_out' => $out,
        ]);
        $msg->save();
        $this->setData('messages_count', (int) $this->getData('messages_count') + 1)
            ->setData('tokens_in', (int) $this->getData('tokens_in') + $in)
            ->setData('tokens_out', (int) $this->getData('tokens_out') + $out)
            ->setData('updated_at', date('Y-m-d H:i:s'));
        if (!$this->getData('title') && $role === 'user' && $content) {
            $this->setData('title', mb_substr($content, 0, 120));
        }
        return $msg;
    }

    /** @return AiNative_Copilot_Model_Message[] */
    public function getMessages(int $limit = 200): array
    {
        $out = [];
        $collection = Mage::getResourceModel('ainative_copilot/message_collection')->addFieldToFilter('conversation_id', (int) $this->getId())->setOrder('message_id', 'ASC')->setPageSize($limit);
        foreach ($collection as $m) {
            $out[] = $m;
        }
        return $out;
    }
}
