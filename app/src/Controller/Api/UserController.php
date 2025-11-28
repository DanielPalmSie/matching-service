<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UserController
{
    public function __construct(private readonly UserService $userService)
    {
    }

    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
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
