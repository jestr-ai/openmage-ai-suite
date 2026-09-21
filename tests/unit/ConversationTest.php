<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ConversationTest extends TestCase
{
    public function testRoundTripAndTrimKeepsToolPairsIntact(): void
    {
        $c = AiNative_Core_Model_Conversation::create('sys')
            ->addUser('u1')->addAssistant(null, [['id' => 'c1', 'name' => 't', 'arguments' => []]])->addToolResults([['id' => 'c1', 'name' => 't', 'content' => '{}', 'is_error' => false]])->addAssistant('a1')
            ->addUser('u2')->addAssistant('a2');
        $copy = AiNative_Core_Model_Conversation::fromArray($c->toArray());
        self::assertSame(6, $copy->count());
        $copy->trim(3); // would cut inside the tool pair; must realign to a user message
        self::assertSame('user', $copy->getMessages()[0]['role']);
        self::assertSame('u2', $copy->getMessages()[0]['text']);
    }
}
