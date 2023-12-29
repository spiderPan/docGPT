<?php

namespace Pan\DocGpt;

use GuzzleHttp\Client;
use Pan\DocGpt\OpenAI\APIClient;
use Pan\DocGpt\OpenAI\OpenAIClient;
use Pan\DocGpt\VectorDB\PgvectorClient;
use Pan\DocGpt\VectorDB\VectorDBClient;
use PDO;

class docGPT
{
    public OpenAIClient   $openai;
    public VectorDBClient $pgvector;

    private array $history_contexts = [];

    private ?Logger $logger = null;


    // These two constants are used to split the text into batches, so that we don't exceed the OpenAI API limits
    // For English text, 1 token is approximately 4 characters, and here are some common model limits:
    // 	- GPT-3.5-turbo: 4096 tokens (16k characters, approximately 3k words)
    // 	- GPT-3.5-turbo-16k: 4096*4 tokens (64k characters, approximately 12k words) [selected]

    // For the model selected, we estimate the response to be no more than 2k characters (300 - 500 words),
    // so we set the request limit to 14k characters (2k words), which should be greater than LEARN_BATCH_SIZE * CONTEXT_BATCH_LIMITS

    private const LEARN_BATCH_SIZE     = 3000; # characters
    private const CONTEXT_BATCH_LIMITS = 12;

    /**
     * @throws \Exception
     */
    public function __construct(string $openai_apikey, PDO $vectordb_pdo)
    {
        $this->openai   = new APIClient($openai_apikey);
        $this->pgvector = new PgvectorClient($vectordb_pdo);
    }


    public function set_logger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    public function add_history_context(string $context): void
    {
        $this->history_contexts[] = $context;
    }

    public function reset_history_contexts(): void
    {
        $this->history_contexts = [];
    }

    /**
     * @throws \Exception
     */
    public function learn(string $text, ?string $namespace = null): bool
    {
        $namespace = $namespace ?? md5($text);
        if ($this->pgvector->is_namespace_exist($namespace)) {
            return true;
        }

        $batches = $this->split_text_into_batches($text, self::LEARN_BATCH_SIZE);

        foreach ($batches as $batch) {
            $response = $this->openai->embeddings($batch);

            if (empty($response['data'][0]['embedding'])) {
                throw new \Exception('Invalid response' . json_encode($response));
            }

            $embedding = $response['data'][0]['embedding'];
            $count     = $this->pgvector->insert(embedding: $embedding, namespace: $namespace, text: $batch);
            if ($count === 0) {
                throw new \Exception('Failed to insert');
            }
        }

        return true;
    }


    /**
     * @throws \Exception
     */
    public function get_contexts(string $text, array|string|null $namespace = null): array
    {
        $result   = [];
        $response = $this->openai->embeddings($text);

        if (empty($response['data'][0]['embedding'])) {
            throw new \Exception('Invalid response' . json_encode($response));
        }

        $embedding = $response['data'][0]['embedding'];
        $rows      = $this->pgvector->search(embedding: $embedding, namespace: $namespace, limit: self::CONTEXT_BATCH_LIMITS);

        if (empty($rows)) {
            throw new \Exception('Failed to search');
        }

        foreach ($rows as $row) {
            $result[] = $row['text'];
        }

        return $result;
    }


    /**
     * @throws \Exception
     */
    public function chat(string $text, array|string|null $namespace = null): string
    {
        $contexts = $this->get_contexts($text, $namespace);

        if ($this->logger) {
            $this->logger->log('debug', "Context retrieved:");
            $this->logger->log('debug', $contexts);

            $this->logger->log('debug', 'History context:');
            $this->logger->log('debug', $this->history_contexts);

            $this->logger->log('debug', 'Namespace found:');
            $this->logger->log('debug', $namespace);
        }
        $messages = [];

        $contexts = array_merge($contexts, $this->history_contexts);

        foreach ($contexts as $context) {
            $messages[] = ['role' => 'system', 'content' => $context];
        }

        $messages[] = ['role' => 'user', 'content' => $text];

        $response = $this->openai->chat($messages);

        if (empty($response['choices'])) {
            throw new \Exception('Invalid response' . json_encode($response));
        }

        $top_choice = array_shift($response['choices']);

        if ($top_choice['message']['role'] !== 'assistant') {
            throw new \Exception('Invalid role in ' . json_encode($top_choice));
        }

        if ($top_choice['finish_reason'] !== 'stop') {
            throw new \Exception('Invalid finish_reason in the top choice:' . json_encode($top_choice));
        }

        return $top_choice['message']['content'];
    }

    private function split_text_into_batches($long_text, $max_batch_length = 500): array
    {
        // Remove leading and trailing whitespace characters from each paragraph
        $paragraphs = preg_split('/\n+/', trim($long_text));

        $batches       = [];
        $current_batch = '';

        foreach ($paragraphs as $paragraph) {
            // Check if adding the current paragraph to the batch exceeds the maximum length
            if (strlen($current_batch . $paragraph) > $max_batch_length) {
                // If adding the paragraph exceeds the limit, start a new batch
                $batches[]     = $current_batch;
                $current_batch = $paragraph;
            } else {
                // Otherwise, add the paragraph to the current batch
                $current_batch .= $paragraph;
            }
        }

        // Add the last batch
        $batches[] = $current_batch;

        return $batches;
    }

}


