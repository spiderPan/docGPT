<?php

namespace Pan\DocGpt\VectorDB;

use PDO;
use Pgvector\Vector;

//TODO: implement common methods from https://github.com/pgvector/pgvector#storing
class PgvectorClient implements VectorDBClient
{
    private PDO $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    public function insert(array $embedding, string $namespace = '', string $text = ''): int
    {
        $query     = 'INSERT INTO items ( namespace, text, embedding) VALUES (:namespace, :text, :embedding)';
        $statement = $this->db->prepare($query);
        $statement->bindValue(':namespace', $namespace);
        $statement->bindValue(':text', $text);
        $statement->bindValue(':embedding', $this->construct_vector($embedding));
        $statement->execute();

        return $statement->rowCount();
    }

    public function is_namespace_exist(string $namespace): bool
    {
        $query     = 'SELECT * FROM items WHERE namespace = :namespace';
        $statement = $this->db->prepare($query);
        $statement->bindValue(':namespace', $namespace);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function search(array $embedding, array|string|null $namespace = null, int $limit = 10): array
    {
        $query       = 'SELECT * FROM items';
        $bind_values = [
            ':embedding' => $this->construct_vector($embedding),
            ':limit'     => $limit,
        ];

        if (!empty($namespace)) {
            if (is_array($namespace)) {
                // If $namespace is an array, create placeholders for IN clause
                $placeholders = [];
                foreach ($namespace as $count => $item) {
                    $placeholders[]                   = ":namespace_$count";
                    $bind_values[":namespace_$count"] = $item;
                }
                $query .= ' WHERE namespace IN (' . implode(',', $placeholders) . ')';
            } else {
                // If $namespace is not an array, use the equality condition
                $query                     .= ' WHERE namespace = :namespace';
                $bind_values[':namespace'] = $namespace;
            }
        }
        $query .= ' ORDER BY embedding <-> :embedding LIMIT :limit';

        $stmt = $this->db->prepare($query);

        foreach ($bind_values as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function truncate(): bool
    {
        $statement = $this->db->prepare('TRUNCATE TABLE items');

        return $statement->execute();
    }

    function construct_vector($text): object
    {
        return new Vector($text);
    }
}
