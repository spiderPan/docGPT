<?php

namespace Pan\DocGpt;

use Faker\Factory;
use Faker\Generator;
use Pan\DocGpt\Logger\FileLogger;
use Pan\DocGpt\Logger\Logger;
use Pan\DocGpt\Model\Step;
use Pan\DocGpt\Model\Steps;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DocGPTTests extends TestCase
{
    private DocGPT    $docGPT;
    private Generator $faker;

    private Logger $logger;

    protected function setUp(): void
    {
        $this->faker  = Factory::create();
        $openAiClient = new OpenAI\MockClient();

        $pdo            = new PDO('pgsql:host=pgvector;dbname=docGPT', 'docGPT', 'doc_gpt_password');
        $pgvectorClient = new VectorDB\PgvectorClient($pdo);
        $pgvectorClient->truncate();

        $this->docGPT = new DocGPT($openAiClient, $pgvectorClient);

        // Set up a filelogger
        $this->logger = new FileLogger(sys_get_temp_dir() . '/logs');
        $this->docGPT->setLogger($this->logger);
    }

    protected function reset(): void
    {
        array_map('unlink', glob(sys_get_temp_dir() . '/logs/*.log'));
    }

    public function testSplitTextIntoBatches()
    {
        $reflection = new ReflectionClass($this->docGPT);
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

    public function testItCanChat(): void
    {
        $text = $this->faker->paragraphs(2000, true);
        $this->assertTrue($this->docGPT->learn($text));

        $request_message   = 'How are you?';
        $expected_response = [];
        // The mock will return the request message as the response message in JSON format
        $response_message = $this->docGPT->chat($request_message);
        $this->assertNotEmpty($response_message);
        // The response should contain the contexts and history_context as the system message

        $this->assertResponse($request_message, $response_message);
    }

    public function testItCanMultipleStepChat()
    {
        // Ensure learned
        $text = $this->faker->paragraphs(200, true);
        $this->assertTrue($this->docGPT->learn($text));

        $steps = new Steps();

        // Add 2 steps that valid
        $steps->addStep(new Step('Step 1 Prompt'));
        $steps->addStep(new Step('Step 2 Prompt'));

        // Add 2 steps that invalid
        $steps->addStep(new Step('Step 3 Prompt', ['Step']));
        $steps->addStep(new Step('Step 4 Prompt', ['4']));

        $this->assertCount(4, $steps->getSteps());

        // The mock will return the request message as the response message in JSON format
        $responses = $this->docGPT->multiStepsChat($steps);
        $this->assertCount(2, $responses);

        // Validate the response with two steps
        $this->assertResponse('Step 1 Prompt', $responses[0]);
        $this->assertResponse('Step 2 Prompt', $responses[1], [$responses[0]]);

        // Ensure the history context set with two responses
        $history_context = $steps->getHistoryContexts();
        $this->assertCount(2, $history_context);
        $this->assertEquals($responses, $history_context);

        // Test it can reset the history context
        $steps->resetHistoryContexts();
        $this->assertEmpty($steps->getHistoryContexts());

        // Two of steps would cause error
        $error_entries = $this->logger->getLogEntries(['type' => 'error']);
        $this->assertCount(2, $error_entries);
    }

    public function testItCanMultipleStepChatWithStopCondition()
    {
        // Ensure learned
        $text = $this->faker->paragraphs(200, true);
        $this->assertTrue($this->docGPT->learn($text));

        // Set up logger
        $steps = new Steps();

        // Let's set a conditional stop at step 3
        $count = 0;
        $steps->setStopCondition(function () use (&$count) {
            $count++;
            if ($count > 3) {
                return true;
            }

            return false;
        });


        // Add 4 steps that valid
        $steps->addStep(new Step('Step 1 Prompt'));
        $steps->addStep(new Step('Step 2 Prompt'));
        $steps->addStep(new Step('Step 3 Prompt'));
        $steps->addStep(new Step('Step 4 Prompt'));

        $this->assertCount(4, $steps->getSteps());


        // The mock will return the request message as the response message in JSON format
        $responses = $this->docGPT->multiStepsChat($steps);
        $this->assertCount(3, $responses);

        // Validate the response with two steps
        $this->assertResponse('Step 1 Prompt', $responses[0]);
        $this->assertResponse('Step 2 Prompt', $responses[1], [$responses[0]]);

        // Ensure the history context set with two responses
        $history_context = $steps->getHistoryContexts();
        $this->assertCount(3, $history_context);
        $this->assertEquals($responses, $history_context);

        // Test it can reset the history context
        $steps->resetHistoryContexts();
        $this->assertEmpty($steps->getHistoryContexts());

        // No error
        $error_entries = $this->logger->getLogEntries(['type' => 'error']);
        $this->assertCount(0, $error_entries);

        // Test it has the condition met message in info
        $info_entries = $this->logger->getLogEntries(['type' => 'info']);
        $this->assertNotEmpty($info_entries);

        $conditional_met_info = array_pop($info_entries);
        $this->assertEquals('🛑️ Stop condition met, exiting loop', $conditional_met_info['content']);
    }

    private function assertResponse($request_message, $actual_response, $extra_context = []): void
    {
        $contexts = $this->docGPT->getContexts($request_message);
        $this->assertNotEmpty($contexts);

        $contexts = array_merge($contexts, $extra_context);
        foreach ($contexts as $context) {
            $expected_response[] = ['content' => $context, 'role' => 'system'];
        }

        // The response should contain the request message as the user message
        $expected_response[] = ['content' => $request_message, 'role' => 'user'];

        $this->assertJsonStringEqualsJsonString(json_encode($expected_response), $actual_response);
    }
}
