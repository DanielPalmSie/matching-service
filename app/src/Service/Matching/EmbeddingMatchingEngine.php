<?php

declare(strict_types=1);

namespace App\Service\Matching;

use App\Entity\Request;
use App\Repository\RequestRepository;

class EmbeddingMatchingEngine implements MatchingEngineInterface
{
    public function __construct(
        private readonly RequestRepository $requestRepository,
    ) {
    }

    public function findMatches(Request $request, int $limit = 20): array
    {
        $queryEmbedding = $request->getEmbedding();
        if ($queryEmbedding === null || $request->getEmbeddingStatus() !== 'ready') {
            return [];
        }

        // The legacy PHP cosineSimilarity was replaced with a pgvector search so that
        // Postgres handles distance calculations close to the data.
        $matches = $this->requestRepository->findNearestByEmbedding($request, $queryEmbedding, $limit);

        $results = [];
        foreach ($matches as $match) {
            $matchedRequest = $this->requestRepository->find($match['id']);
            if ($matchedRequest === null) {
                continue;
            }

            $results[] = [
                'request' => $matchedRequest,
                'similarity' => 1 - $match['distance'],
            ];
        }

        return $results;
    }
}
