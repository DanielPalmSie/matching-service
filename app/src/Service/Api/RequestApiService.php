<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Entity\User;
use App\Service\Http\JsonPayloadDecoder;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\Request;

class RequestApiService
{
    public function __construct(
        private readonly RequestService $requestService,
        private readonly JsonPayloadDecoder $payloadDecoder,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(Request $request, User $currentUser): array
    {
        $payload = $this->payloadDecoder->decode($request, 'Invalid JSON body.');

        return $this->requestService->createRequest($payload, $currentUser);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMine(Request $request, User $currentUser): array
    {
        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = $request->query->has('limit') ? (int) $request->query->get('limit') : null;

        return $this->requestService->getRequestsForOwner($currentUser, $offset, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMatches(int $id, Request $request): array
    {
        $limit = (int) $request->query->get('limit', 20);

        return $this->requestService->getMatchesData($id, $limit);
    }
}
