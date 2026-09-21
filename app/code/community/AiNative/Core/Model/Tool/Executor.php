<?php

/**
 * Runs a tool for a context: schema validation, write gating, ACL, rate limiting, audit.
 * @license MIT
 */
class AiNative_Core_Model_Tool_Executor
{
    public const MAX_RESULT_CHARS = 60000;

    /**
     * @return array{content: string, is_error: bool, data: array|null}
     */
    public function run(AiNative_Core_Model_Tool_Interface $tool, array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $helper = Mage::helper('ainative_core');
        $audit = Mage::getModel('ainative_core/audit');
        $started = microtime(true);
        try {
            if (!$helper->isEnabled()) {
                throw new AiNative_Core_Exception('AI Suite is disabled in configuration.');
            }
            if ($tool->isWrite()) {
                if (!$helper->isWriteAllowed()) {
                    $audit->denied($context, $tool, $args, 'write tools disabled globally');
                    throw new AiNative_Core_Exception('Write tools are disabled by the store administrator (AI Suite > Security > Allow Write Tools).');
                }
                if (!$context->canWrite()) {
                    $audit->denied($context, $tool, $args, 'actor lacks write scope');
                    throw new AiNative_Core_Exception('This connection is read-only. Use a token with write scope.');
                }
            }
            $aclResource = $tool->getAclResource();
            if ($aclResource !== null && !Mage::getSingleton('ainative_core/acl')->isAllowed($context, $aclResource)) {
                $audit->denied($context, $tool, $args, 'acl ' . $aclResource);
                throw new AiNative_Core_Exception(sprintf('Access denied: your admin role lacks permission "%s".', $aclResource));
            }
            Mage::getSingleton('ainative_core/rateLimit')->hit($context->getRateKey());

            $clean = Mage::getSingleton('ainative_core/tool_schema')->validate($tool->getInputSchema(), $args);
            $data = $tool->execute($clean, $context);
            if ($helper->isRedactPii() && !$context->isAdmin()) {
                $data = Mage::helper('ainative_core/redact')->data($data);
            }
            $content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
            if (mb_strlen($content) > self::MAX_RESULT_CHARS) {
                $content = mb_substr($content, 0, self::MAX_RESULT_CHARS) . '…"[truncated]"}';
            }
            $audit->tool($context, $tool, $clean, $content, (int) ((microtime(true) - $started) * 1000));
            return ['content' => $content, 'is_error' => false, 'data' => $data];
        } catch (AiNative_Core_Exception $e) {
            $msg = $e->getMessage();
            $audit->error($context, $tool->getName(), $args, $msg, (int) ((microtime(true) - $started) * 1000));
            return ['content' => json_encode(['error' => $msg]) ?: '{"error":"unknown"}', 'is_error' => true, 'data' => null];
        } catch (Throwable $e) {
            $helper->log(sprintf('tool %s crashed: %s', $tool->getName(), $e->getMessage()), ['trace' => $e->getTraceAsString()], Zend_Log::ERR);
            $audit->error($context, $tool->getName(), $args, get_class($e) . ': ' . $e->getMessage(), (int) ((microtime(true) - $started) * 1000));
            return ['content' => json_encode(['error' => 'The tool failed unexpectedly. The store administrator can find details in var/log/ainative.log.']) ?: '{}', 'is_error' => true, 'data' => null];
        }
    }
}
