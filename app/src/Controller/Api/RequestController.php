<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RequestController
{
    public function __construct(private readonly RequestService $requestService)
    {
    }

    #[Route('/api/requests', name: 'api_requests_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $data = $this->requestService->createRequest($payload);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/requests/{id}', name: 'api_requests_get', methods: ['GET'])]
    public function getRequest(int $id): JsonResponse
    {
        try {
            $data = $this->requestService->getRequestData($id);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/requests/{id}/matches', name: 'api_requests_matches', methods: ['GET'])]
    public function getMatches(int $id, Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 20);

        try {
            $data = $this->requestService->getMatchesData($id, $limit);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($data);
    }
}
