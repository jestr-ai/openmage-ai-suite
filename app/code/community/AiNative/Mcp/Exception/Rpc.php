<?php

class AiNative_Mcp_Exception_Rpc extends Exception
{
    public function __construct(int $code, string $message, private readonly mixed $data = null)
    {
        parent::__construct($message, $code);
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}
