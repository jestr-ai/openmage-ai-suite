<?php

/**
 * Loads tool definitions from config.xml (global/ainative_tools/{scope}/{name}/class).
 * @license MIT
 */
class AiNative_Core_Model_Tool_Registry
{
    public const SCOPE_ADMIN = 'admin';
    public const SCOPE_STOREFRONT = 'storefront';

    /** @var array<string, array<string, AiNative_Core_Model_Tool_Interface>> */
    private array $cache = [];

    /**
     * @return array<string, AiNative_Core_Model_Tool_Interface> name => tool
     */
    public function getTools(string $scope): array
    {
        if (isset($this->cache[$scope])) {
            return $this->cache[$scope];
        }
        $tools = [];
        $node = Mage::getConfig()->getNode('global/ainative_tools/' . $scope);
        if ($node) {
            foreach ($node->children() as $name => $def) {
                if (isset($def->disabled) && (string) $def->disabled === '1') {
                    continue;
                }
                $class = (string) ($def->class ?? '');
                if ($class === '') {
                    continue;
                }
                try {
                    $tool = Mage::getModel($class);
                } catch (Throwable $e) {
                    Mage::helper('ainative_core')->log("Tool {$name}: could not instantiate {$class}: " . $e->getMessage(), null, Zend_Log::ERR);
                    continue;
                }
                if ($tool instanceof AiNative_Core_Model_Tool_Interface) {
                    $tools[$tool->getName()] = $tool;
                }
            }
        }
        ksort($tools);
        $this->cache[$scope] = $tools;
        return $tools;
    }

    public function getTool(string $scope, string $name): ?AiNative_Core_Model_Tool_Interface
    {
        return $this->getTools($scope)[$name] ?? null;
    }

    /**
     * Tools the given context may see: filters write tools when the actor cannot write and, for admins, by ACL.
     *
     * @param  string[]|null $allowList explicit allow-list of tool names (MCP token restriction)
     * @return array<string, AiNative_Core_Model_Tool_Interface>
     */
    public function getToolsFor(string $scope, AiNative_Core_Model_Tool_Context $context, ?array $allowList = null): array
    {
        $acl = Mage::getSingleton('ainative_core/acl');
        $writeAllowed = Mage::helper('ainative_core')->isWriteAllowed() && $context->canWrite();
        $out = [];
        foreach ($this->getTools($scope) as $name => $tool) {
            if ($allowList !== null && !in_array($name, $allowList, true)) {
                continue;
            }
            if ($tool->isWrite() && !$writeAllowed) {
                continue;
            }
            if ($tool->getAclResource() !== null && !$acl->isAllowed($context, $tool->getAclResource())) {
                continue;
            }
            $out[$name] = $tool;
        }
        return $out;
    }
}
