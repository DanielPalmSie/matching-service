<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Low-level access to the pgvector-backed user_embeddings table.
 */
class UserEmbeddingRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Stores or updates the embedding vector for the given user.
     *
     * @param array<int, float> $embedding
     */
    public function upsert(int $userId, array $embedding): void
    {
        $this->connection->executeStatement(
            'INSERT INTO user_embeddings (user_id, embedding) VALUES (:user_id, :embedding)
            ON CONFLICT (user_id) DO UPDATE SET embedding = EXCLUDED.embedding',
            [
                'user_id' => $userId,
                'embedding' => $this->formatVector($embedding),
            ],
            [
                'user_id' => ParameterType::INTEGER,
                'embedding' => ParameterType::STRING,
            ],
        );
    }

    /**
     * Finds nearest neighbours ordered by cosine distance via the <-> operator.
     *
     * @param array<int, float> $embedding
     *
     * @return array<int, array{user_id: int, distance: float}>
     */
    public function findNearest(array $embedding, int $limit = 20): array
    {
        $results = $this->connection->executeQuery(
            'SELECT user_id, (embedding <-> :query_vector) AS distance
             FROM user_embeddings
             ORDER BY embedding <-> :query_vector
             LIMIT :limit',
            [
                'query_vector' => $this->formatVector($embedding),
                'limit' => $limit,
            ],
            [
                'query_vector' => ParameterType::STRING,
                'limit' => ParameterType::INTEGER,
            ],
        )->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'user_id' => (int) $row['user_id'],
                'distance' => (float) $row['distance'],
            ],
            $results,
        );
    }

    /**
     * Converts a PHP array into the textual representation pgvector expects.
     *
     * @param array<int, float> $embedding
     */
    private function formatVector(array $embedding): string
    {
        return '[' . implode(',', array_map(static fn ($value) => sprintf('%.12f', $value), $embedding)) . ']';
    }
}
