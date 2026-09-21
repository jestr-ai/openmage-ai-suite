<?php

/**
 * A tool the model may call. Registered in config.xml under global/ainative_tools/{admin|storefront}.
 * @license MIT
 */
interface AiNative_Core_Model_Tool_Interface
{
    /** snake_case, unique within its scope */
    public function getName(): string;

    /** Shown to the model. Say what it does, what it returns, when to use it. */
    public function getDescription(): string;

    /** JSON Schema (type: object). */
    public function getInputSchema(): array;

    /** Admin ACL resource path (e.g. admin/catalog/products), or null when no ACL applies (storefront). */
    public function getAclResource(): ?string;

    /** Write tools mutate data; they need write scope and the global allow_write_tools switch. */
    public function isWrite(): bool;

    /**
     * @return array<string, mixed> JSON-serialisable result
     * @throws AiNative_Core_Exception on user-facing errors (returned to the model as an error result)
     */
    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array;
}
