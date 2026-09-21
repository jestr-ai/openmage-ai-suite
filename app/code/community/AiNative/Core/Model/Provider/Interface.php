<?php

/**
 * Provider-neutral chat completion with tool calling.
 * @license MIT
 */
interface AiNative_Core_Model_Provider_Interface
{
    public function getCode(): string;

    public function getModel(): string;

    public function setStoreId(?int $storeId): self;

    public function isConfigured(): bool;

    /**
     * One model turn. Tools are AiNative_Core_Model_Tool_Interface definitions (or already-built schema arrays).
     *
     * @param  AiNative_Core_Model_Tool_Interface[]|array<int, array{name:string,description:string,input_schema:array}> $tools
     * @param  array{max_tokens?:int,temperature?:float,json?:bool} $options
     * @throws AiNative_Core_Exception
     */
    public function complete(AiNative_Core_Model_Conversation $conversation, array $tools = [], array $options = []): AiNative_Core_Model_Turn;
}
