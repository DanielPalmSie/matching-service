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
    #[OA\Post(path: '/api/chats/{userId}/start', tags: ['Chats'])]
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
    #[OA\Get(path: '/api/chats', tags: ['Chats'])]
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
    #[OA\Get(path: '/api/chats/{chatId}/messages', tags: ['Chats'])]
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
    #[OA\Post(path: '/api/chats/{chatId}/messages', tags: ['Chats'])]
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
    #[OA\Post(path: '/api/chats/{chatId}/messages/{messageId}/read', tags: ['Chats'])]
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
