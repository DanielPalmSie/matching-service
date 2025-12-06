<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use App\Service\RequestService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RequestController extends AbstractController
{
    public function __construct(private readonly RequestService $requestService)
    {
    }

    #[Route('/api/requests', name: 'api_requests_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/requests',
        summary: 'Create a request',
        description: 'Creates a new request with embedding for later matching operations.',
        tags: ['Requests'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['rawText', 'type'],
                properties: [
                    new OA\Property(property: 'ownerId', type: 'integer', example: 10, description: 'Deprecated. Owner is derived from the authenticated user and this field must match that value if provided.'),
                    new OA\Property(property: 'rawText', type: 'string', example: 'Looking for a software engineer role in Berlin', description: 'Full free-text content of the request.'),
                    new OA\Property(property: 'type', type: 'string', example: 'job', description: 'Short type name of the request.'),
                    new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Berlin', description: 'Optional city for geo filtering.'),
                    new OA\Property(property: 'country', type: 'string', nullable: true, example: 'DE', description: 'Optional ISO country code.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Request created successfully.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 42),
                        new OA\Property(property: 'ownerId', type: 'integer', example: 10),
                        new OA\Property(property: 'type', type: 'string', example: 'job'),
                        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Berlin'),
                        new OA\Property(property: 'country', type: 'string', nullable: true, example: 'DE'),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-05-01T12:00:00+00:00'),
                        new OA\Property(property: 'rawText', type: 'string', example: 'Looking for a software engineer role in Berlin'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_BAD_REQUEST,
                description: 'Validation failed (e.g. missing rawText/type or mismatched owner).',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string')])
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Owner could not be found.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string')])
            ),
            new OA\Response(response: Response::HTTP_INTERNAL_SERVER_ERROR, description: 'Unexpected error while creating the request.'),
        ],
    )]
    public function create(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $data = $this->requestService->createRequest($payload, $currentUser);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/requests/{id}', name: 'api_requests_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/requests/{id}',
        summary: 'Get request details',
        description: 'Retrieves a single request by its identifier.',
        tags: ['Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Request identifier'),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Request found.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 42),
                        new OA\Property(property: 'ownerId', type: 'integer', example: 10),
                        new OA\Property(property: 'type', type: 'string', example: 'job'),
                        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Berlin'),
                        new OA\Property(property: 'country', type: 'string', nullable: true, example: 'DE'),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-05-01T12:00:00+00:00'),
                        new OA\Property(property: 'rawText', type: 'string', example: 'Looking for a software engineer role in Berlin'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Request not found.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string')])
            ),
            new OA\Response(response: Response::HTTP_INTERNAL_SERVER_ERROR, description: 'Unexpected error while fetching the request.'),
        ],
    )]
    public function getRequest(int $id): JsonResponse
    {
        try {
            $data = $this->requestService->getRequestData($id);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/requests/mine', name: 'api_requests_my_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/requests/mine',
        summary: 'List requests for the current user',
        description: 'Returns active requests owned by the authenticated user.',
        tags: ['Requests'],
        parameters: [
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 0, default: 0), description: 'Pagination offset (optional).'),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), description: 'Maximum number of records to return (optional).'),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'List of active requests owned by the current user.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 42),
                            new OA\Property(property: 'ownerId', type: 'integer', example: 10),
                            new OA\Property(property: 'type', type: 'string', example: 'job'),
                            new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Berlin'),
                            new OA\Property(property: 'country', type: 'string', nullable: true, example: 'DE'),
                            new OA\Property(property: 'status', type: 'string', example: 'active'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-05-01T12:00:00+00:00'),
                            new OA\Property(property: 'rawText', type: 'string', example: 'Looking for a software engineer role in Berlin'),
                        ],
                    ),
                ),
            ),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'User not authenticated.'),
        ],
    )]
    public function listMine(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = $request->query->has('limit') ? (int) $request->query->get('limit') : null;

        $data = $this->requestService->getRequestsForOwner($currentUser, $offset, $limit);

        return new JsonResponse($data);
    }

    #[Route('/api/requests/{id}/matches', name: 'api_requests_matches', methods: ['GET'])]
    #[OA\Get(
        path: '/api/requests/{id}/matches',
        summary: 'Get matching requests',
        description: 'Returns similar requests for the given request using the matching engine.',
        tags: ['Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Request identifier'),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20),
                description: 'Maximum number of matches to return (1-100).'
            ),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Matching requests returned.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 84),
                            new OA\Property(property: 'ownerId', type: 'integer', example: 15),
                            new OA\Property(property: 'type', type: 'string', example: 'job'),
                            new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Berlin'),
                            new OA\Property(property: 'country', type: 'string', nullable: true, example: 'DE'),
                            new OA\Property(property: 'status', type: 'string', example: 'active'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-05-01T12:00:00+00:00'),
                        ],
                    ),
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'Request not found.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string')])
            ),
            new OA\Response(response: Response::HTTP_INTERNAL_SERVER_ERROR, description: 'Unexpected error while fetching matches.'),
        ],
    )]
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
