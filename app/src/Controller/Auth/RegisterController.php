<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\RegisterService;
use App\Service\Exception\ConflictException;
use App\Service\Exception\ValidationException;
use App\Service\Http\JsonPayloadDecoder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly RegisterService $registerService,
        private readonly JsonPayloadDecoder $payloadDecoder,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Register a new user',
        description: 'Creates a user account with email and password credentials.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'new.user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'SecurePass123!'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'User registered successfully.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'string', example: 'ok')]),
            ),
            new OA\Response(
                response: Response::HTTP_BAD_REQUEST,
                description: 'Invalid payload or missing required fields.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string', example: 'Email and password are required.')]),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'User already exists.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string', example: 'User already exists.')]),
            ),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $this->payloadDecoder->decode($request, 'Invalid payload.');
            $this->registerService->register($payload);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (ConflictException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['status' => 'ok'], Response::HTTP_CREATED);
    }
}
