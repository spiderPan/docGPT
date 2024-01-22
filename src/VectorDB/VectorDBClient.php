<?php

namespace Pan\DocGpt\VectorDB;

interface VectorDBClient
{
    public function constructVector($text): object;

    public function insert(array $embedding, string $namespace = '', string $text = ''): int;

    public function isNamespaceExist(string $namespace): bool;

    public function search(array $embedding, array|string|null $namespace = null, int $limit = 10): array;
}
