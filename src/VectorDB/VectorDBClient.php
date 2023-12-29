<?php

namespace Pan\DocGpt\VectorDB;

interface VectorDBClient
{
    function constructVector($text): object;

    function insert(array $embedding, string $namespace = '', string $text = ''): int;

    function isNamespaceExist(string $namespace): bool;

    function search(array $embedding, array|string|null $namespace = null, int $limit = 10): array;
}
