<?php

namespace Pan\DocGpt\VectorDB;

interface VectorDBClient
{
    function construct_vector($text): object;

    function insert(array $embedding, string $namespace = '', string $text = ''): int;

    function is_namespace_exist(string $namespace): bool;

    function search(array $embedding, array|string|null $namespace = null, int $limit = 10): array;
}
