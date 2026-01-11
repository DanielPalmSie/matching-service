<?php

declare(strict_types=1);

namespace App\Service\Embedding;

/**
 * @phpstan-type Embedding array<float>
 */
interface EmbeddingClientInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;
}
