<?php

/**
 * Agent loop: prompt → provider → tools → provider … until a text answer.
 * Used by Copilot (admin chat), Assistant (storefront chat) and bulk jobs. MCP does not use it.
 * @license MIT
 */
class AiNative_Core_Model_Agent
{
    /** @var callable|null fn(AiNative_Core_Model_ToolCall $call, array $result): void */
    private $onToolResult = null;

    /** @var callable|null fn(AiNative_Core_Model_ToolCall $call): bool  return false to ask the human first */
    private $confirmWrite = null;

    private array $pendingConfirmations = [];

    public function onToolResult(callable $cb): self
    {
        $this->onToolResult = $cb;
        return $this;
    }

    public function confirmWriteWith(callable $cb): self
    {
        $this->confirmWrite = $cb;
        return $this;
    }

    /**
     * @param  array<string, AiNative_Core_Model_Tool_Interface> $tools
     * @return array{text: string, tool_calls: int, tokens_in: int, tokens_out: int, pending_confirmations: array, refusal: bool, truncated: bool}
     * @throws AiNative_Core_Exception
     */
    public function run(
        AiNative_Core_Model_Conversation $conversation,
        array $tools,
        AiNative_Core_Model_Tool_Context $context,
        ?AiNative_Core_Model_Provider_Interface $provider = null,
        array $options = [],
    ): array {
        $helper = Mage::helper('ainative_core');
        if (!$helper->isEnabled($context->getStoreId())) {
            throw new AiNative_Core_Exception('AI Suite is disabled.');
        }
        $provider ??= $helper->getProvider(null, $context->getStoreId());
        if (!$provider->isConfigured()) {
            throw new AiNative_Core_Exception(sprintf('Provider "%s" is not configured (missing API key or model).', $provider->getCode()));
        }
        Mage::getSingleton('ainative_core/usage')->assertBudget();
        Mage::getSingleton('ainative_core/rateLimit')->hit($context->getRateKey());

        $executor = Mage::getSingleton('ainative_core/tool_executor');
        $audit = Mage::getModel('ainative_core/audit');
        $usage = Mage::getSingleton('ainative_core/usage');
        $maxIterations = (int) ($options['max_iterations'] ?? $helper->getMaxIterations($context->getStoreId()));
        $totalIn = 0;
        $totalOut = 0;
        $toolCalls = 0;
        $finalText = '';
        $refusal = false;
        $truncated = false;
        $this->pendingConfirmations = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            $started = microtime(true);
            $turn = $provider->complete($conversation, array_values($tools), $options);
            $totalIn += $turn->getTokensIn();
            $totalOut += $turn->getTokensOut();
            $audit->llm($context, $provider, $turn, (int) ((microtime(true) - $started) * 1000));
            $usage->record($provider->getCode(), $turn->getModel(), $context->getChannel(), $turn->getTokensIn(), $turn->getTokensOut());

            $conversation->addTurn($turn);
            if ($turn->isRefusal()) {
                $refusal = true;
                $finalText = (string) ($turn->getText() ?? '');
                break;
            }
            if (!$turn->hasToolCalls()) {
                $finalText = (string) ($turn->getText() ?? '');
                $truncated = $turn->isTruncated();
                break;
            }
            $results = [];
            foreach ($turn->getToolCalls() as $call) {
                $toolCalls++;
                $tool = $tools[$call->name] ?? null;
                if (!$tool) {
                    $results[] = ['id' => $call->id, 'name' => $call->name, 'content' => json_encode(['error' => "Unknown tool {$call->name}"]), 'is_error' => true];
                    continue;
                }
                if ($tool->isWrite() && $this->confirmWrite && !($this->confirmWrite)($call)) {
                    $this->pendingConfirmations[] = $call->toArray();
                    $results[] = ['id' => $call->id, 'name' => $call->name, 'content' => json_encode(['status' => 'pending_confirmation', 'message' => 'This change was shown to the user for confirmation. Tell the user what you proposed and stop.']), 'is_error' => false];
                    continue;
                }
                $result = $executor->run($tool, $call->arguments, $context);
                if ($this->onToolResult) {
                    ($this->onToolResult)($call, $result);
                }
                $results[] = ['id' => $call->id, 'name' => $call->name, 'content' => $result['content'], 'is_error' => $result['is_error']];
            }
            $conversation->addToolResults($results);
        }
        if ($finalText === '' && !$refusal) {
            $finalText = $helper->__('I could not finish answering within the allowed number of steps. Please narrow the question.');
        }
        return [
            'text' => $finalText,
            'tool_calls' => $toolCalls,
            'tokens_in' => $totalIn,
            'tokens_out' => $totalOut,
            'pending_confirmations' => $this->pendingConfirmations,
            'refusal' => $refusal,
            'truncated' => $truncated,
        ];
    }

    /**
     * One-shot generation without tools (Copilot content generation).
     */
    public function generate(string $system, string $prompt, AiNative_Core_Model_Tool_Context $context, array $options = []): string
    {
        $conversation = AiNative_Core_Model_Conversation::create($system)->addUser($prompt);
        $result = $this->run($conversation, [], $context, null, $options + ['max_iterations' => 1]);
        return $result['text'];
    }
}
