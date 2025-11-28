<?php

declare(strict_types=1);

namespace App\Service\Matching;

use App\Entity\Request;
use App\Repository\RequestRepository;

class EmbeddingMatchingEngine implements MatchingEngineInterface
{
    public function __construct(private readonly RequestRepository $requestRepository)
    {
    }

    public function findMatches(Request $request, int $limit = 20): array
    {
        $embedding = $request->getEmbedding();
        if ($embedding === null) {
            return [];
        }

        $candidates = $this->requestRepository->findActiveCandidates($request);
        $scored = [];

        foreach ($candidates as $candidate) {
            $candidateEmbedding = $candidate->getEmbedding();
            if ($candidateEmbedding === null) {
                continue;
            }

            $similarity = $this->cosineSimilarity($embedding, $candidateEmbedding);
            $scored[] = ['request' => $candidate, 'score' => $similarity];
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(static fn ($item) => $item['request'], array_slice($scored, 0, $limit));
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($a), count($b));
        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
