<?php

namespace Pan\DocGpt;

use Pan\DocGpt\Logger\Logger;
use Pan\DocGpt\Logger\SystemLogger;
use Pan\DocGpt\Model\Step;
use Pan\DocGpt\Model\Steps;
use Pan\DocGpt\OpenAI\OpenAIClient;
use Pan\DocGpt\VectorDB\VectorDBClient;

class DocGPT
{
    public OpenAIClient $openai;

    public VectorDBClient $pgvector;

    private Logger $logger;


    // These two constants are used to split the text into batches, so that we don't exceed the OpenAI API limits
    // For English text, 1 token is approximately 4 characters, and here are some common model limits:
    // 	- GPT-3.5-turbo: 4096 tokens (16k characters, approximately 3k words)
    // 	- GPT-3.5-turbo-16k: 4096*4 tokens (64k characters, approximately 12k words) [selected]

    // For the model selected, we estimate the response to be no more than 2k characters (300 - 500 words),
    // so we set the request limit to 14k characters (2k words), which should be greater than LEARN_BATCH_SIZE * CONTEXT_BATCH_LIMITS

    private const LEARN_BATCH_SIZE     = 3000; # characters
    private const CONTEXT_BATCH_LIMITS = 12;

    public function __construct(OpenAIClient $openai_client, VectorDBClient $pgvector_client)
    {
        $this->openai   = $openai_client;
        $this->pgvector = $pgvector_client;
        $this->logger   = new SystemLogger();
    }

    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    public function learn(string $text): bool
    {
        $namespace = md5($text);
        if ($this->pgvector->isNamespaceExist($namespace)) {
            return true;
        }

        $batches = $this->splitTextIntoBatches($text, self::LEARN_BATCH_SIZE);

        foreach ($batches as $batch) {
            $response = $this->openai->embeddings($batch);

            if (empty($response['data'][0]['embedding'])) {
                $this->logger->log('error', 'Invalid response' . json_encode($response));

                return false;
            }

            $embedding = $response['data'][0]['embedding'];
            $count     = $this->pgvector->insert(embedding: $embedding, namespace: $namespace, text: $batch);
            if ($count === 0) {
                $this->logger->log('error', 'Failed to insert');

                return false;
            }
        }

        return true;
    }

    public function getContexts(string $text, array|string|null $namespace = null): array
    {
        $result   = [];
        $response = $this->openai->embeddings($text);

        if (empty($response['data'][0]['embedding'])) {
            $this->logger->log('error', 'Invalid response' . json_encode($response));

            return [];
        }

        $embedding = $response['data'][0]['embedding'];
        $rows      = $this->pgvector->search(embedding: $embedding, namespace: $namespace, limit: self::CONTEXT_BATCH_LIMITS);

        if (empty($rows)) {
            $this->logger->log('error', 'Failed to search');

            return [];
        }

        foreach ($rows as $row) {
            $result[] = $row['text'];
        }

        return $result;
    }


    public function chat(string $text, array|string|null $namespace = null, array $history_context = []): string
    {
        $contexts = $this->getContexts($text, $namespace);

        $this->logger->log('debug', "Context retrieved:");
        $this->logger->log('debug', $contexts);

        $this->logger->log('debug', 'History context:');
        $this->logger->log('debug', $history_context);

        $this->logger->log('debug', 'Namespace found:');
        $this->logger->log('debug', $namespace ?: '');

        $messages = [];

        $contexts = array_merge($contexts, $history_context);

        foreach ($contexts as $context) {
            $messages[] = ['role' => 'system', 'content' => $context];
        }

        $messages[] = ['role' => 'user', 'content' => $text];

        $response = $this->openai->chat($messages);

        if (empty($response['choices'])) {
            $this->logger->log('error', 'Invalid response' . json_encode($response));

            return '';
        }

        $top_choice = array_shift($response['choices']);

        if ($top_choice['message']['role'] !== 'assistant') {
            $this->logger->log('error', 'Invalid role in ' . json_encode($top_choice));

            return '';
        }

        if ($top_choice['finish_reason'] !== 'stop') {
            $this->logger->log('error', 'Invalid finish_reason in the top choice:' . json_encode($top_choice));

            return '';
        }

        return $top_choice['message']['content'];
    }

    public function multiStepsChat(Steps $sequential_steps, array|string|null $namespace = null): array
    {
        $this->logger->log('debug', 'Starting Multiple Steps Chat');
        $total_steps  = $sequential_steps->count();
        $step_results = [];

        foreach ($sequential_steps as $i => $sequential_step) {
            $is_step_success = false;
            $step            = $i + 1;
            /**
             * @var Step $sequential_step
             */
            $prompt_text = $sequential_step->getPrompt();

            $this->logger->log('debug', 'Prompt constructed:');
            $this->logger->log('debug', $prompt_text);

            $max_attempts = 3; // Maximum number of attempts
            $attempts     = 0;
            while ($attempts < $max_attempts) {
                if ($sequential_steps->checkStopCondition()) {
                    $is_step_success = true;
                    $this->logger->log('info', '🛑️ Stop condition met, exiting loop');
                    break;
                }
                $attempts++;

                $response = $this->chat($prompt_text, $namespace, $sequential_steps->getHistoryContexts());
                $this->logger->log('debug', "[$attempts/$max_attempts] Attempts -- Response from OpenAI received:");
                $this->logger->log('debug', $response);

                // Check if the content is unexpected
                if (!$sequential_step->isValidResponse($response)) {
                    // Content is as expected, proceed with further processing
                    $is_step_success = true;
                    $sequential_steps->addHistoryContext($response);
                    $step_results[] = $response;
                    $this->logger->log('info', "🚀 Step $step / $total_steps processed successfully!");
                    break;
                }
            }

            if (!$is_step_success) {
                $this->logger->log('error', "💥 Step $step / $total_steps failed!");
            }
        }

        return $step_results;
    }

    private function splitTextIntoBatches($long_text, $max_batch_length = 500): array
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

        // Remove empty batches
        return array_filter($batches);
    }

}
