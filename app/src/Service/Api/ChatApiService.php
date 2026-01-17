<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Dto\Message\MessageDTO;
use App\Entity\Chat;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ChatRepository;
use App\Repository\MessageRepository;
use App\Repository\RequestRepository;
use App\Repository\UserRepository;
use App\Service\Chat\ChatDtoFactory;
use App\Service\Chat\ChatService;
use App\Service\Exception\AccessDeniedException;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use App\Service\Http\JsonPayloadDecoder;
use Symfony\Component\HttpFoundation\Request;
use App\Dto\Chat\ChatListItemDTO;

class ChatApiService
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly ChatRepository $chatRepository,
        private readonly MessageRepository $messageRepository,
        private readonly ChatDtoFactory $chatDtoFactory,
        private readonly UserRepository $userRepository,
        private readonly RequestRepository $requestRepository,
        private readonly JsonPayloadDecoder $payloadDecoder,
    ) {
    }

    /**
     * @return ChatListItemDTO
     */
    public function startChat(User $currentUser, int $userId, ?string $originType = null, ?int $originId = null): ChatListItemDTO
    {
        $otherUser = $this->userRepository->find($userId);
        if (!$otherUser instanceof User) {
            throw new NotFoundException('User not found.');
        }

        $chat = $this->chatService->createOrGetChat($currentUser, $otherUser, $originType, $originId);
        $originRequest = null;
        if ($originType === 'request' && $originId !== null) {
            $originRequest = $this->requestRepository->find($originId);
        }

        return $this->chatDtoFactory->createChatListItem($chat, $currentUser, $originRequest);
    }

    /**
     * @return array<int, ChatListItemDTO>
     */
    public function listChats(User $currentUser): array
    {
        $chats = $this->chatRepository->findChatsForUser($currentUser);

        return array_map(
            fn (array $row) => $this->chatDtoFactory->createChatListItem(
                $row['chat'],
                $currentUser,
                $row['originRequest'] ?? null
            ),
            $chats
        );
    }

    /**
     * @return array<int, MessageDTO>
     */
    public function listMessages(User $currentUser, int $chatId, Request $request): array
    {
        $chat = $this->findChatForUser($currentUser, $chatId);

        $offset = max(0, (int) $request->query->get('offset', 0));
        $limit = max(1, min(200, (int) $request->query->get('limit', 50)));

        $messages = $this->messageRepository->findMessagesForChat($chat, $offset, $limit);

        return $this->chatDtoFactory->createMessageList($messages);
    }

    public function sendMessage(User $currentUser, int $chatId, Request $request): MessageDTO
    {
        $chat = $this->findChatForUser($currentUser, $chatId);

        $payload = $this->payloadDecoder->decode($request, 'Invalid payload: content is required.');
        if (!isset($payload['content']) || !is_string($payload['content'])) {
            throw new ValidationException('Invalid payload: content is required.');
        }

        $message = $this->chatService->sendMessage($chat, $currentUser, $payload['content']);

        return $this->chatDtoFactory->createMessageDto($message);
    }

    public function markRead(User $currentUser, int $chatId, int $messageId): void
    {
        $chat = $this->chatRepository->find($chatId);
        if (!$chat instanceof Chat) {
            throw new NotFoundException('Chat not found.');
        }

        $message = $this->messageRepository->find($messageId);
        if (!$message instanceof Message || $message->getChat()->getId() !== $chat->getId()) {
            throw new NotFoundException('Message not found.');
        }

        if (!$chat->getParticipants()->contains($currentUser)) {
            throw new AccessDeniedException('Forbidden.');
        }

        $this->chatService->markMessageAsRead($message, $currentUser);
    }

    private function findChatForUser(User $currentUser, int $chatId): Chat
    {
        $chat = $this->chatRepository->find($chatId);
        if (!$chat instanceof Chat) {
            throw new NotFoundException('Chat not found.');
        }

        if (!$chat->getParticipants()->contains($currentUser)) {
            throw new AccessDeniedException('Forbidden.');
        }

        return $chat;
    }
}
