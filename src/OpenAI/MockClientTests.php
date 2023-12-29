<?php

namespace Pan\DocGpt\OpenAI;

use PHPUnit\Framework\TestCase;

class MockClientTests extends TestCase
{
    private MockClient $mockClient;

    protected function setUp(): void
    {
        $this->mockClient = new MockClient();
    }

    public function testEmbeddings()
    {
        $result = $this->mockClient->embeddings('test');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('object', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('usage', $result);
    }

    public function testChat()
    {
        $result = $this->mockClient->chat(messages: ['test'], model: 'test-model');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('choices', $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertEquals('test-model', $result['model']);
        $this->assertArrayHasKey('object', $result);
        $this->assertArrayHasKey('usage', $result);
    }
}
