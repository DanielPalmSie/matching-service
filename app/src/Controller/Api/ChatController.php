<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Api\ChatApiService;
use App\Service\Exception\AccessDeniedException;
use App\Service\Exception\ValidationException;
use App\Service\Exception\NotFoundException;
use App\Service\Http\JsonPayloadDecoder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Chat endpoints now rely on the authenticated user from the JWT; the legacy X-User-Id header is intentionally ignored.
 */
class ChatController extends AbstractController
{
    public function __construct(
        private readonly ChatApiService $chatApiService,
        private readonly JsonPayloadDecoder $payloadDecoder,
    ) {
    }

    #[Route('/api/chats/{userId}/start', name: 'api_chats_start', methods: ['POST'])]
    #[OA\Post(
        path: '/api/chats/{userId}/start',
        summary: 'Create or reuse a chat between two users',
        description: 'Returns an existing chat for the current user and the given user or creates a new one if none exists.',
        tags: ['Chats'],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'Identifier of the other participant.'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'originType', type: 'string', example: 'request'),
                    new OA\Property(property: 'originId', type: 'integer', example: 123),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Chat returned (existing or newly created).',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 15),
                        new OA\Property(
                            property: 'participants',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 7),
                                    new OA\Property(property: 'displayName', type: 'string', nullable: true, example: 'Ada Lovelace'),
                                ],
                            ),
                        ),
                        new OA\Property(
                            property: 'lastMessage',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 120),
                                new OA\Property(property: 'chatId', type: 'integer', example: 15),
                                new OA\Property(property: 'senderId', type: 'integer', example: 7),
                                new OA\Property(property: 'content', type: 'string', example: 'Hello there!'),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-06-01T12:00:00+00:00'),
                                new OA\Property(property: 'isRead', type: 'boolean', example: false),
                            ],
                        ),
                        new OA\Property(property: 'unreadCount', type: 'integer', example: 3, description: 'Unread messages for the current user'),
                        new OA\Property(property: 'title', type: 'string', example: 'Need help moving a sofa…'),
                        new OA\Property(property: 'subtitle', type: 'string', nullable: true, example: 'Berlin, DE'),
                        new OA\Property(
                            property: 'context',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'type', type: 'string', example: 'request'),
                                new OA\Property(property: 'id', type: 'integer', example: 123),
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'User not authenticated.'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Other participant not found.'),
            new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid origin payload.'),
        ],
    )]
    public function startChat(int $userId, Request $request): JsonResponse
    {
        $currentUser = $this->requireAuthenticatedUser();
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $originType = null;
            $originId = null;
            $rawContent = trim((string) $request->getContent());

            if ($rawContent !== '') {
                $payload = $this->payloadDecoder->decode($request, 'Invalid payload: originType or originId is required.');
                if (array_key_exists('originType', $payload)) {
                    if (!is_string($payload['originType']) || $payload['originType'] !== 'request') {
                        throw new ValidationException('Invalid payload: originType must be "request".');
                    }
                    $originType = $payload['originType'];
                }

                if (array_key_exists('originId', $payload)) {
                    if (!is_int($payload['originId']) || $payload['originId'] <= 0) {
                        throw new ValidationException('Invalid payload: originId must be a positive integer.');
                    }
                    $originId = $payload['originId'];
                }

                if ($originType !== null && $originId === null) {
                    throw new ValidationException('Invalid payload: originId is required when originType is provided.');
                }

                if ($originType === null && $originId !== null) {
                    throw new ValidationException('Invalid payload: originType is required when originId is provided.');
                }
            }

            $data = $this->chatApiService->startChat($currentUser, $userId, $originType, $originId);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($data);
    }

    #[Route('/api/chats', name: 'api_chats_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/chats',
        summary: 'List chats for the current user',
        description: 'Returns chat threads where the current user is a participant, sorted by the last message.',
        tags: ['Chats'],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'List of chats returned.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 15),
                            new OA\Property(
                                property: 'participants',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 7),
                                        new OA\Property(property: 'displayName', type: 'string', nullable: true, example: 'Ada Lovelace'),
                                    ],
                                ),
                            ),
                            new OA\Property(
                                property: 'lastMessage',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 120),
                                    new OA\Property(property: 'chatId', type: 'integer', example: 15),
                                    new OA\Property(property: 'senderId', type: 'integer', example: 7),
                                    new OA\Property(property: 'content', type: 'string', example: 'Hello there!'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-06-01T12:00:00+00:00'),
                                    new OA\Property(property: 'isRead', type: 'boolean', example: false),
                                ],
                            ),
                            new OA\Property(property: 'unreadCount', type: 'integer', example: 3, description: 'Unread messages for the current user'),
                            new OA\Property(property: 'title', type: 'string', example: 'Need help moving a sofa…'),
                            new OA\Property(property: 'subtitle', type: 'string', nullable: true, example: 'Berlin, DE'),
                            new OA\Property(
                                property: 'context',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'type', type: 'string', example: 'request'),
                                    new OA\Property(property: 'id', type: 'integer', example: 123),
                                ],
                            ),
                        ],
                    ),
                ),
            ),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'User not authenticated.'),
        ],
    )]
    public function listChats(Request $request): JsonResponse
    {
        $currentUser = $this->requireAuthenticatedUser();
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $items = $this->chatApiService->listChats($currentUser);

        return new JsonResponse($items);
    }

    #[Route('/api/chats/{chatId}/messages', name: 'api_chats_messages', methods: ['GET'])]
    #[OA\Get(
        path: '/api/chats/{chatId}/messages',
        summary: 'List messages for a chat',
        description: 'Returns paginated messages for the specified chat if the current user participates in it.',
        tags: ['Chats'],
        parameters: [
            new OA\Parameter(name: 'chatId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Chat identifier'),
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 0, default: 0), description: 'Pagination offset'),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 200, default: 50), description: 'Pagination limit'),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Messages returned.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 120),
                            new OA\Property(property: 'chatId', type: 'integer', example: 15),
                            new OA\Property(property: 'senderId', type: 'integer', example: 7),
                            new OA\Property(property: 'content', type: 'string', example: 'Hello there!'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-06-01T12:00:00+00:00'),
                            new OA\Property(property: 'isRead', type: 'boolean', example: false),
                        ],
                    ),
                ),
            ),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'User not authenticated.'),
            new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Current user is not a participant.'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Chat not found.'),
        ],
    )]
    public function listMessages(int $chatId, Request $request): JsonResponse
    {
        $currentUser = $this->requireAuthenticatedUser();
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $messages = $this->chatApiService->listMessages($currentUser, $chatId, $request);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (AccessDeniedException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($messages);
    }

    #[Route('/api/chats/{chatId}/messages', name: 'api_chats_messages_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/chats/{chatId}/messages',
        summary: 'Send a message in a chat',
        description: 'Creates a message in the given chat if the current user is a participant and publishes it to Mercure.',
        tags: ['Chats'],
        parameters: [
            new OA\Parameter(name: 'chatId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Chat identifier'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', example: 'Hello there!'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Message created and returned.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 120),
                        new OA\Property(property: 'chatId', type: 'integer', example: 15),
                        new OA\Property(property: 'senderId', type: 'integer', example: 7),
                        new OA\Property(property: 'content', type: 'string', example: 'Hello there!'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-06-01T12:00:00+00:00'),
                        new OA\Property(property: 'isRead', type: 'boolean', example: false),
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Missing or invalid content.'),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'User not authenticated.'),
            new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Current user is not a participant.'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Chat not found.'),
        ],
    )]
    public function sendMessage(int $chatId, Request $request): JsonResponse
    {
        $currentUser = $this->requireAuthenticatedUser();
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $message = $this->chatApiService->sendMessage($currentUser, $chatId, $request);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (AccessDeniedException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($message, Response::HTTP_CREATED);
    }

    #[Route('/api/chats/{chatId}/messages/{messageId}/read', name: 'api_chats_messages_read', methods: ['POST'])]
    #[OA\Post(
        path: '/api/chats/{chatId}/messages/{messageId}/read',
        summary: 'Mark a chat message as read',
        description: 'Marks the given message as read by the current user when they are a chat participant.',
        tags: ['Chats'],
        parameters: [
            new OA\Parameter(name: 'chatId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Chat identifier'),
            new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Message identifier'),
        ],
        responses: [
            new OA\Response(
                response: Response::HTTP_OK,
                description: 'Message marked as read.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'string', example: 'ok')]),
            ),
            new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Validation failed (e.g. marking own message).'),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'User not authenticated.'),
            new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Current user is not a participant.'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Chat or message not found.'),
        ],
    )]
    public function markRead(int $chatId, int $messageId, Request $request): JsonResponse
    {
        $currentUser = $this->requireAuthenticatedUser();
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->chatApiService->markRead($currentUser, $chatId, $messageId);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (NotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (AccessDeniedException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function requireAuthenticatedUser(): ?User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }
}
