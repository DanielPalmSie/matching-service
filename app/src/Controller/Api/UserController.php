<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use App\Service\UserService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController
{
    public function __construct(private readonly UserService $userService)
    {
    }

    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: 'Create or update a user',
        description: 'Creates a user by external identifier or updates details when it already exists.',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['externalId'],
                properties: [
                    new OA\Property(property: 'externalId', type: 'string', example: 'ext-12345', description: 'External unique identifier for the user.'),
                    new OA\Property(property: 'displayName', type: 'string', nullable: true, example: 'Ada Lovelace', description: 'Optional display name.'),
                    new OA\Property(property: 'city', type: 'string', nullable: true, example: 'London', description: 'Optional city.'),
                    new OA\Property(property: 'country', type: 'string', nullable: true, example: 'GB', description: 'Optional ISO country code.'),
                    new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'Europe/London', description: 'Optional timezone identifier.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'User created or updated.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 7),
                        new OA\Property(property: 'externalId', type: 'string', example: 'ext-12345'),
                        new OA\Property(property: 'displayName', type: 'string', nullable: true, example: 'Ada Lovelace'),
                        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'London'),
                        new OA\Property(property: 'country', type: 'string', nullable: true, example: 'GB'),
                        new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'Europe/London'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-05-01T12:00:00+00:00'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_BAD_REQUEST,
                description: 'Validation failed (e.g. missing externalId).',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string')])
            ),
            new OA\Response(response: Response::HTTP_INTERNAL_SERVER_ERROR, description: 'Unexpected error while saving the user.'),
        ],
    )]
    public function createOrUpdate(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid payload: externalId is required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $data = $this->userService->createOrUpdate($payload);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/users/{id}', name: 'api_users_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Get user data',
        description: 'Retrieves a user by identifier with core profile fields.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'User identifier'),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'User found.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 7),
                        new OA\Property(property: 'externalId', type: 'string', example: 'ext-12345'),
                        new OA\Property(property: 'displayName', type: 'string', nullable: true, example: 'Ada Lovelace'),
                        new OA\Property(property: 'city', type: 'string', nullable: true, example: 'London'),
                        new OA\Property(property: 'country', type: 'string', nullable: true, example: 'GB'),
                        new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'Europe/London'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-05-01T12:00:00+00:00'),
                    ],
                ),
            ),
            new OA\Response(
                response: Response::HTTP_NOT_FOUND,
                description: 'User not found.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string')])
            ),
            new OA\Response(response: Response::HTTP_INTERNAL_SERVER_ERROR, description: 'Unexpected error while fetching the user.'),
        ],
    )]
    public function getUser(int $id): JsonResponse
    {
        try {
            $data = $this->userService->getUserData($id);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($data);
    }
}
