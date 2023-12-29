<?php

namespace Pan\DocGpt;

use PDO;
use PHPUnit\Framework\TestCase;

class DocGPTTests extends TestCase
{
    private docGPT $docGPT;

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $openAiClient = new OpenAI\MockClient();

        $pdo            = new PDO('pgsql:host=pgvector;dbname=docGPT', 'docGPT', 'doc_gpt_password');
        $pgvectorClient = new VectorDB\PgvectorClient($pdo);
        $pgvectorClient->truncate();

        $this->docGPT = new docGPT($openAiClient, $pgvectorClient);
    }

    public function testLearn(): void
    {
        $text = 'test text';
        $this->assertTrue($this->docGPT->learn($text));
        //TODO: ensure the text has been split into batches and inserted into the database
    }


}
