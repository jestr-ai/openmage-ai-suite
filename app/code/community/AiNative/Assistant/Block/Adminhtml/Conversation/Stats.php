<?php

/**
 * Small KPI strip above the transcripts grid.
 */
class AiNative_Assistant_Block_Adminhtml_Conversation_Stats extends Mage_Adminhtml_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ainative/assistant/stats.phtml');
    }

    public function getStats(int $days = 30): array
    {
        $resource = Mage::getSingleton('core/resource');
        $conn = $resource->getConnection('core_read');
        $table = $resource->getTableName('ainative_copilot/conversation');
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $row = $conn->fetchRow(
            $conn->select()->from($table, [
                'conversations' => 'COUNT(*)',
                'messages' => 'COALESCE(SUM(messages_count),0)',
                'tokens' => 'COALESCE(SUM(tokens_in + tokens_out),0)',
                'customers' => "SUM(actor_type = 'customer')",
            ])->where('channel = ?', 'assistant')->where('created_at >= ?', $since),
        );
        $metas = $conn->fetchCol($conn->select()->from($table, 'meta_json')->where('channel = ?', 'assistant')->where('created_at >= ?', $since)->where('meta_json IS NOT NULL'));
        $cards = 0;
        $handoffs = 0;
        foreach ($metas as $m) {
            $d = json_decode((string) $m, true) ?: [];
            $cards += (int) ($d['product_cards'] ?? 0);
            $handoffs += !empty($d['handoffs']) ? 1 : 0;
        }
        $msgTable = $resource->getTableName('ainative_copilot/message');
        $top = $conn->fetchAll(
            $conn->select()->from(['m' => $msgTable], ['q' => 'LOWER(LEFT(m.content, 80))', 'n' => 'COUNT(*)'])
                ->join(['c' => $table], 'c.conversation_id = m.conversation_id', [])
                ->where('c.channel = ?', 'assistant')->where("m.role = 'user'")->where('m.created_at >= ?', $since)
                ->group('q')->order('n DESC')->limit(10),
        );
        return [
            'days' => $days,
            'conversations' => (int) ($row['conversations'] ?? 0),
            'messages' => (int) ($row['messages'] ?? 0),
            'tokens' => (int) ($row['tokens'] ?? 0),
            'customers' => (int) ($row['customers'] ?? 0),
            'product_cards' => $cards,
            'handoffs' => $handoffs,
            'top_questions' => $top,
        ];
    }
}
