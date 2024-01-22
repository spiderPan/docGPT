<?php

namespace Pan\DocGpt\OpenAI;

use PHPUnit\Framework\TestCase;

class MockClientTests extends TestCase
{
    private MockClient $mockClient;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        $this->mockClient = new MockClient();
        $this->faker      = \Faker\Factory::create();
    }

    public function testEmbeddings()
    {
        $text = $this->faker->text();

        $result = $this->mockClient->embeddings($text);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('object', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('usage', $result);

        $result2 = $this->mockClient->embeddings($text);
        $this->assertEquals($result, $result2);
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
