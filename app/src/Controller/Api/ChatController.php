<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Message\CreateMessageDTO;
use App\Entity\Chat;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ChatRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\Chat\ChatDtoFactory;
use App\Service\Chat\ChatService;
use App\Service\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ChatController
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ChatRepository $chatRepository,
        private readonly MessageRepository $messageRepository,
        private readonly ChatDtoFactory $chatDtoFactory,
        private readonly UserRepository $userRepository,
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
            new OA\Parameter(
                name: 'X-User-Id',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'Identifier of the authenticated user (passed through the existing gateway/security layer).'
            ),
        ],
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
                    ],
                ),
            ),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'User not authenticated.'),
            new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Other participant not found.'),
        ],
    )]
    public function startChat(int $userId, Request $request): JsonResponse
    {
        $currentUser = $this->resolveCurrentUser($request);
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $otherUser = $this->userRepository->find($userId);
        if (!$otherUser instanceof User) {
            return new JsonResponse(['error' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $chat = $this->chatService->createOrGetChat($currentUser, $otherUser);

        return new JsonResponse($this->chatDtoFactory->createChatListItem($chat));
    }

    #[Route('/api/chats', name: 'api_chats_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/chats',
        summary: 'List chats for the current user',
        description: 'Returns chat threads where the current user is a participant, sorted by the last message.',
        tags: ['Chats'],
        parameters: [
            new OA\Parameter(
                name: 'X-User-Id',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'Identifier of the authenticated user (passed through the existing gateway/security layer).'
            ),
        ],
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
                        ],
                    ),
                ),
            ),
            new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'User not authenticated.'),
        ],
    )]
    public function listChats(Request $request): JsonResponse
    {
        $currentUser = $this->resolveCurrentUser($request);
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $chats = $this->chatRepository->findChatsForUser($currentUser);
        $items = array_map(fn (Chat $chat) => $this->chatDtoFactory->createChatListItem($chat), $chats);

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
            new OA\Parameter(
                name: 'X-User-Id',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'Identifier of the authenticated user (passed through the existing gateway/security layer).'
            ),
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
        $currentUser = $this->resolveCurrentUser($request);
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $chat = $this->chatRepository->find($chatId);
        if (!$chat instanceof Chat) {
            return new JsonResponse(['error' => 'Chat not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$chat->getParticipants()->contains($currentUser)) {
            return new JsonResponse(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = max(1, min(200, (int) $request->query->get('limit', 50)));

        $messages = $this->messageRepository->findMessagesForChat($chat, $offset, $limit);

        return new JsonResponse($this->chatDtoFactory->createMessageList($messages));
    }

    #[Route('/api/chats/{chatId}/messages', name: 'api_chats_messages_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/chats/{chatId}/messages',
        summary: 'Send a message in a chat',
        description: 'Creates a message in the given chat if the current user is a participant and publishes it to Mercure.',
        tags: ['Chats'],
        parameters: [
            new OA\Parameter(name: 'chatId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Chat identifier'),
            new OA\Parameter(
                name: 'X-User-Id',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'Identifier of the authenticated user (passed through the existing gateway/security layer).'
            ),
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
        $currentUser = $this->resolveCurrentUser($request);
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $chat = $this->chatRepository->find($chatId);
        if (!$chat instanceof Chat) {
            return new JsonResponse(['error' => 'Chat not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$chat->getParticipants()->contains($currentUser)) {
            return new JsonResponse(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['content']) || !is_string($payload['content'])) {
            return new JsonResponse(['error' => 'Invalid payload: content is required.'], Response::HTTP_BAD_REQUEST);
        }

        $dto = new CreateMessageDTO($payload['content']);

        try {
            $message = $this->chatService->sendMessage($chat, $currentUser, $dto->content);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->chatDtoFactory->createMessageDto($message), Response::HTTP_CREATED);
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
            new OA\Parameter(
                name: 'X-User-Id',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'Identifier of the authenticated user (passed through the existing gateway/security layer).'
            ),
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
        $currentUser = $this->resolveCurrentUser($request);
        if ($currentUser === null) {
            return new JsonResponse(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $chat = $this->chatRepository->find($chatId);
        if (!$chat instanceof Chat) {
            return new JsonResponse(['error' => 'Chat not found.'], Response::HTTP_NOT_FOUND);
        }

        $message = $this->messageRepository->find($messageId);
        if (!$message instanceof Message || $message->getChat()->getId() !== $chat->getId()) {
            return new JsonResponse(['error' => 'Message not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$chat->getParticipants()->contains($currentUser)) {
            return new JsonResponse(['error' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->chatService->markMessageAsRead($message, $currentUser);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function resolveCurrentUser(Request $request): ?User
    {
        $userId = $request->attributes->getInt('userId') ?: $request->headers->get('X-User-Id');
        if ($userId === null || $userId === '') {
            return null;
        }

        return $this->userRepository->find((int) $userId);
    }
}
