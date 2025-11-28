<?php

declare(strict_types=1);

namespace App\Service\Embedding;

/**
 * @phpstan-type Embedding array<float>
 */
interface EmbeddingClientInterface
{
    /**
     * @return Embedding
     */
    public function embed(string $text): array;
}
