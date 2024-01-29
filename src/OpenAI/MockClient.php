<?php

namespace Pan\DocGpt\OpenAI;

use Faker\Factory;

class MockClient implements OpenAIClient
{
    private \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    // OpenAI API methods
    public function embeddings(string $text, $model = 'text-embedding-ada-002'): array
    {
        $seed = crc32($text);
        $this->faker->seed($seed);
        $tokens = $this->faker->numberBetween(0, 100);

        return [
            'data'   => [
                [
                    'embedding' => $this->generateRandomEmbedding(3072),
                    'index'     => 0,
                    'object'    => 'embedding'
                ]
            ],
            'object' => 'list',
            'model'  => $model,
            'usage'  => [
                'prompt_tokens' => $tokens,
                'total_tokens'  => $tokens,
            ],
        ];
    }


    /**
     * This chat method will mock the structure of the OpenAI chat API response
     * BUT return the actual $messages from the request.
     */
    public function chat(array $messages, $model = 'gpt-3.5-turbo-16k'): array
    {
        $prompt_tokens     = $this->faker->numberBetween(0, 100);
        $completion_tokens = $this->faker->numberBetween(0, 100);
        $total_tokens      = $prompt_tokens + $completion_tokens;

        return [
            'choices' => [
                [
                    'finish_reason' => 'stop',
                    'index'         => 0,
                    'message'       => [
                        'content' => json_encode($messages),
                        'role'    => 'assistant'
                    ],
                    'logprobs'      => null
                ]
            ],
            'created' => $this->faker->unixTime,
            'id'      => 'chatcmpl-' . $this->faker->uuid,
            'object'  => 'chat.completion',
            'model'   => $model,
            'usage'   => [
                'prompt_tokens'     => $prompt_tokens,
                'completion_tokens' => $completion_tokens,
                'total_tokens'      => $total_tokens,
            ],
        ];
    }

    private function generateRandomEmbedding(int $size): array
    {
        $embedding = [];
        for ($i = 0; $i < $size; $i++) {
            $embedding[] = $this->faker->randomFloat(); // Generates a random float between 0 and 1
        }

        return $embedding;
    }
}
