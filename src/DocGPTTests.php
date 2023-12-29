<?php

namespace Pan\DocGpt;

use Faker\Factory;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DocGPTTests extends TestCase
{
    private docGPT           $docGPT;
    private \Faker\Generator $faker;

    /**
     * @throws \Exception
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
        $method->setAccessible(true);

        $paragraphs  = $this->faker->paragraphs(10);
        $batch_sizes = [10, 100, 1000];
        // Test with different batch sizes
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

    public function testLearn(): void
    {
        $text = 'test text';
        $this->assertTrue($this->docGPT->learn($text));
        //TODO: ensure the text has been split into batches and inserted into the database
    }


}
