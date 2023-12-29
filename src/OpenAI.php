<?php

namespace Pan\DocGpt\OpenAI;

use GuzzleHttp\Client;

class OpenAI
{
    private \OpenAI\Client $client;

    public function __construct($api_key, Client $http_client)
    {
        if (!class_exists('\OpenAI')) {
            throw new \Exception('OpenAI library is not installed');
        }

        $this->client = \OpenAI::factory()
                               ->withApiKey($api_key)
                               ->withHttpClient($http_client)
                               ->make();
    }

    // OpenAI API methods
    public function embeddings(string $text, $model = 'text-embedding-ada-002', $force = false)
    {
        $response = $this->client->embeddings()->create([
            'model' => $model,
            'input' => $text,
        ]);

        return $response->toArray();
    }

    public function completions($prompt, $max_tokens = 6, $temperature = 0, $model = 'text-davinci-003')
    {
        $response = $this->client->completions()->create([
            'model'       => $model,
            'prompt'      => $prompt,
            'max_tokens'  => $max_tokens,
            'temperature' => $temperature,
        ]);

        return $response->toArray();
    }

    /**
     * @throws \Exception
     */
    public function chat(array $messages, $model = 'gpt-3.5-turbo-16k')
    {
        $roles = $this->list_pluck($messages, 'role');

        if (array_diff(array_unique($roles), ['system', 'user', 'assistant', 'function'])) {
            throw new \Exception('Invalid roles');
        }

        $response = $this->client->chat()->create([
            'model'    => $model,
            'messages' => $messages,
        ]);

        return $response->toArray();
    }

    private function list_pluck($array, $field)
    {
        return array_map(function ($item) use ($field) {
            return $item->$field;
        }, $array);
    }
}
