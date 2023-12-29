<?php

namespace Pan\DocGpt;

use Exception;
use Faker\Factory;
use Faker\Generator;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DocGPTTests extends TestCase
{
    private DocGPT    $docGPT;
    private Generator $faker;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->faker  = Factory::create();
        $openAiClient = new OpenAI\MockClient();

        $pdo            = new PDO('pgsql:host=pgvector;dbname=docGPT', 'docGPT', 'doc_gpt_password');
        $pgvectorClient = new VectorDB\PgvectorClient($pdo);
        $pgvectorClient->truncate();

        $this->docGPT = new docGPT($openAiClient, $pgvectorClient);
    }

    public function testSplitTextIntoBatches()
    {
        $reflection = new ReflectionClass(docGPT::class);
        $method     = $reflection->getMethod('splitTextIntoBatches');

        $paragraphs = $this->faker->paragraphs(20);

        // Test with different batch sizes, including some that are smaller than the paragraphs
        $batch_sizes = [10, 100, 1000, 10000];

        foreach ($batch_sizes as $batch_size) {
            $exceed_batch_size_paragraphs = [];
            foreach ($paragraphs as $paragraph) {
                if (strlen($paragraph) > $batch_size) {
                    $exceed_batch_size_paragraphs[] = $paragraph;
                }
            }

            $text    = implode("\n\n", $paragraphs);
            $batches = $method->invoke($this->docGPT, $text, $batch_size);

            // verify that batch size is not exceeded unless it's one of the paragraphs that exceeds the batch size
            foreach ($batches as $batch) {
                // if the batch is larger than the batch size, it should be one of the paragraphs that exceeds the batch size in order
                if (strlen($batch) > $batch_size) {
                    $this->assertEquals($batch, array_shift($exceed_batch_size_paragraphs));
                }
            }

            // At this point, all the paragraphs that exceed the batch size should have been processed, so the array should be empty
            $this->assertEmpty($exceed_batch_size_paragraphs);

            // The text should be the same as the original text, minus the newlines
            $batchText = implode("", $batches);
            $this->assertEquals(str_replace("\n", "", $text), $batchText);
        }
    }

    public function testLearnAndGetAsContext(): void
    {
        $text    = $this->faker->paragraphs(20, true);
        $rawText = str_replace("\n", "", $text);
        $this->assertTrue($this->docGPT->learn($text));

        $part     = substr($text, 0, 40);
        $contexts = $this->docGPT->getContexts($part);
        $this->assertNotEmpty($contexts);

        foreach ($contexts as $context) {
            $this->assertStringContainsString($context, $rawText);
        }
    }

    public function testItCanAddHistoryContext(): void
    {
        $expected_history_contexts = [
            'I am a highly intelligent question answering bot.',
            'I answer questions in the English language.',
            'I was trained by the good folks at OpenAI.',
        ];
        $reflection                = new ReflectionClass($this->docGPT);
        $property                  = $reflection->getProperty('history_contexts');
        $property->setAccessible(true); // Allow access to the private property

        $this->docGPT->resetHistoryContexts();
        // Get the value of the private property using reflection
        $this->assertEmpty($property->getValue($this->docGPT));

        foreach ($expected_history_contexts as $expected_history_context) {
            $this->docGPT->addHistoryContext($expected_history_context);
        }

        $this->assertEquals($expected_history_contexts, $property->getValue($this->docGPT));
    }

    public function testItCanChat(): void
    {
        $text = $this->faker->paragraphs(2000, true);
        $this->assertTrue($this->docGPT->learn($text));
        $history_contexts = [
            'I am a highly intelligent question answering bot.',
            'I answer questions in the English language.',
            'I was trained by the good folks at OpenAI.',
        ];

        $this->docGPT->resetHistoryContexts();
        foreach ($history_contexts as $history_context) {
            $this->docGPT->addHistoryContext($history_context);
        }

        $request_message   = 'How are you?';
        $expected_response = [];
        // The mock will return the request message as the response message in JSON format
        $response_message = $this->docGPT->chat($request_message);
        $this->assertNotEmpty($response_message);

        // The response should contain the contexts and history_context as the system message
        $contexts = $this->docGPT->getContexts($request_message);
        $this->assertNotEmpty($contexts);

        $contexts = array_merge($contexts, $history_contexts);
        foreach ($contexts as $context) {
            $expected_response[] = ['content' => $context, 'role' => 'system'];
        }

        // The response should contain the request message as the user message
        $expected_response[] = ['content' => $request_message, 'role' => 'user'];

        $this->assertJsonStringEqualsJsonString(json_encode($expected_response), $response_message);
    }
}
