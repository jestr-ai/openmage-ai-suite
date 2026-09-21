<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    private AiNative_Core_Model_Tool_Schema $schema;

    protected function setUp(): void
    {
        $this->schema = new AiNative_Core_Model_Tool_Schema();
    }

    public function testCoercesTypesAndDropsUnknownKeys(): void
    {
        $def = ['type' => 'object', 'additionalProperties' => false, 'required' => ['q'], 'properties' => [
            'q' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'maximum' => 50], 'flag' => ['type' => 'boolean'], 'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 2],
        ]];
        $out = $this->schema->validate($def, ['q' => 'x', 'limit' => '500', 'flag' => 'yes', 'ids' => ['1', '2', '3'], 'junk' => 1]);
        self::assertSame(['q' => 'x', 'limit' => 50, 'flag' => true, 'ids' => [1, 2]], $out);
    }

    public function testMissingRequiredThrows(): void
    {
        $this->expectException(AiNative_Core_Exception::class);
        $this->schema->validate(['type' => 'object', 'required' => ['q'], 'properties' => ['q' => ['type' => 'string']]], []);
    }

    public function testEnumViolationThrows(): void
    {
        $this->expectException(AiNative_Core_Exception::class);
        $this->schema->validate(['type' => 'object', 'properties' => ['s' => ['type' => 'string', 'enum' => ['a', 'b']]]], ['s' => 'c']);
    }

    public function testNonNumericIntegerThrows(): void
    {
        $this->expectException(AiNative_Core_Exception::class);
        $this->schema->validate(['type' => 'object', 'properties' => ['n' => ['type' => 'integer']]], ['n' => 'abc']);
    }
}
