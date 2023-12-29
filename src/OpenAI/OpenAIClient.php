<?php

namespace Pan\DocGpt\OpenAI;

interface OpenAIClient
{
    public function embeddings(string $text, string $model): array;

    public function chat(array $messages, string $model): array;
}
