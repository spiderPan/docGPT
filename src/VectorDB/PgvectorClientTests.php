<?php

namespace Pan\DocGpt\VectorDB;

use PDO;
use PHPUnit\Framework\TestCase;

class PgvectorClientTests extends TestCase
{
    private PDO            $pdo;
    private PgvectorClient $client;

    protected function setUp(): void
    {
        // Assuming you have a test database setup
        $this->pdo    = new PDO('pgsql:host=pgvector;dbname=docGPT', 'docGPT', 'doc_gpt_password');
        $this->client = new PgvectorClient($this->pdo);
    }

    public function testInsert(): void
    {
        $embedding = $this->generateRandomEmbedding(1536);
        $namespace = $this->generateRandomNamespace();
        $text      = 'test text';

        $rowsAffected = $this->client->insert($embedding, $namespace, $text);

        $this->assertEquals(1, $rowsAffected);
    }

    public function testIsNamespaceExist(): void
    {
        $namespace = $this->generateRandomNamespace();
        $embedding = $this->generateRandomEmbedding(1536);
        $this->assertFalse($this->client->is_namespace_exist($namespace));

        $this->client->insert($embedding, $namespace, 'test text');

        $this->assertTrue($this->client->is_namespace_exist($namespace));
    }

    public function testSearch(): void
    {
        $data               = [];
        $starting_embedding = $this->generateRandomEmbedding(1536);
        $namespace_1        = $this->generateRandomNamespace();
        $namespace_2        = $this->generateRandomNamespace();
        $count              = 20;
        // Construct the data to be inserted,
        // - each embedding is different from the previous one by square of the index, therefore the distance between the embeddings is known and sortable
        // - the namespace is alternating between two namespaces, starting with namespace_1
        // - the text is just a string with an index
        for ($i = 0; $i < $count; $i++) {
            $embedding    = $starting_embedding;
            $embedding[0] = $embedding[0] + $i * $i;
            $namespace    = $i % 2 === 0 ? $namespace_1 : $namespace_2;

            $this->client->insert($embedding, $namespace, 'test text' . $i);
            $data[] = [
                'embedding' => $embedding,
                'namespace' => $namespace,
                'text'      => 'test text' . $i,
            ];
        }

        // Each namespace should have 10 items
        $ns_1_result = $this->client->search($starting_embedding, $namespace_1);
        $ns_2_result = $this->client->search($starting_embedding, $namespace_2);
        $this->assertCount(10, $ns_1_result);
        $this->assertCount(10, $ns_2_result);

        // Check that the results are limited to the specified limit
        $ns_1_result_limit_3 = $this->client->search($starting_embedding, $namespace_1, 3);
        $this->assertCount(3, $ns_1_result_limit_3);

        // Check that the results are sorted by distance
        $this->assertEquals($data[0]['text'], $ns_1_result_limit_3[0]['text']);
        $this->assertEquals($data[2]['text'], $ns_1_result_limit_3[1]['text']);
        $this->assertEquals($data[4]['text'], $ns_1_result_limit_3[2]['text']);

        // Check that the results are sorted by distance across namespaces
        $selected_data = $data[4];
        $results       = $this->client->search(embedding: $selected_data['embedding'], limit: 3);
        $this->assertCount(3, $results);
        $this->assertEquals($selected_data['text'], $results[0]['text']);
        $this->assertEquals($data[3]['text'], $results[1]['text']);
        $this->assertEquals($data[5]['text'], $results[2]['text']);
    }

    private function generateRandomEmbedding(int $size): array
    {
        $embedding = [];
        for ($i = 0; $i < $size; $i++) {
            $embedding[] = mt_rand() / mt_getrandmax(); // Generates a random float between 0 and 1
        }

        return $embedding;
    }

    private function generateRandomNamespace(): string
    {
        return 'test_namespace_' . mt_rand();
    }

}
