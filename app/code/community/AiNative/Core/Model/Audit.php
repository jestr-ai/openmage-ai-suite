<?php

/**
 * @method string getRequestId()
 * @method string getChannel()
 * @method string getKind()
 * @method string getName()
 * @license MIT
 */
class AiNative_Core_Model_Audit extends Mage_Core_Model_Abstract
{
    protected function _construct(): void
    {
        $this->_init('ainative_core/audit');
    }

    public function tool(AiNative_Core_Model_Tool_Context $ctx, AiNative_Core_Model_Tool_Interface $tool, array $args, string $result, int $ms): void
    {
        $this->write($ctx, 'tool', $tool->getName(), $tool->isWrite(), $args, $result, 0, 0, $ms);
    }

    public function llm(AiNative_Core_Model_Tool_Context $ctx, AiNative_Core_Model_Provider_Interface $provider, AiNative_Core_Model_Turn $turn, int $ms): void
    {
        $summary = ['stop' => $turn->getStopReason(), 'tool_calls' => array_column($turn->getToolCallsAsArray(), 'name'), 'text' => Mage::helper('ainative_core')->summarize($turn->getText(), 500)];
        $this->write($ctx, 'llm', $provider->getCode() . '/' . $turn->getModel(), false, [], json_encode($summary) ?: '', $turn->getTokensIn(), $turn->getTokensOut(), $ms);
    }

    public function denied(AiNative_Core_Model_Tool_Context $ctx, AiNative_Core_Model_Tool_Interface $tool, array $args, string $reason): void
    {
        $this->write($ctx, 'denied', $tool->getName(), $tool->isWrite(), $args, $reason, 0, 0, 0);
    }

    public function error(AiNative_Core_Model_Tool_Context $ctx, string $name, array $args, string $message, int $ms): void
    {
        $this->write($ctx, 'error', $name, false, $args, $message, 0, 0, $ms);
    }

    private function write(AiNative_Core_Model_Tool_Context $ctx, string $kind, string $name, bool $isWrite, array $args, string $result, int $in, int $out, int $ms): void
    {
        try {
            $helper = Mage::helper('ainative_core');
            $row = Mage::getModel('ainative_core/audit');
            $row->setData([
                'request_id' => $ctx->getRequestId(),
                'channel' => $ctx->getChannel(),
                'actor_type' => $ctx->getActorType(),
                'actor_id' => $ctx->getActorId(),
                'actor_label' => mb_substr($ctx->getActorLabel(), 0, 128),
                'store_id' => $ctx->getStoreId(),
                'kind' => $kind,
                'name' => mb_substr($name, 0, 128),
                'is_write' => $isWrite ? 1 : 0,
                'args_json' => $helper->summarize($args, 8000),
                'result_summary' => $helper->summarize($result, 8000),
                'tokens_in' => $in,
                'tokens_out' => $out,
                'duration_ms' => $ms,
            ]);
            $row->save();
        } catch (Throwable $e) {
            Mage::helper('ainative_core')->log('audit write failed: ' . $e->getMessage(), null, Zend_Log::ERR);
        }
    }
}
