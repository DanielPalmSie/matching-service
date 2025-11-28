<?php

declare(strict_types=1);

namespace App\Service\Matching;

use App\Entity\Request;

interface MatchingEngineInterface
{
    /**
     * @return Request[]
     */
    public function findMatches(Request $request, int $limit = 20): array;
}
