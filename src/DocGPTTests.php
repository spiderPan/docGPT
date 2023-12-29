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
    private DocGPT           $docGPT;
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


}
