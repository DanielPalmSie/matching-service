<?php

declare(strict_types=1);

namespace App\Service\Chat;

use App\Dto\Chat\ChatListItemDTO;
use App\Dto\Message\MessageDTO;
use App\Entity\Chat;
use App\Entity\Message;
use App\Repository\MessageRepository;

class ChatDtoFactory
{
    public function __construct(private readonly MessageRepository $messageRepository)
    {
    }

    public function createChatListItem(Chat $chat): ChatListItemDTO
    {
        $lastMessage = $this->messageRepository->findLastMessageForChat($chat);

        return ChatListItemDTO::fromChat($chat, $lastMessage);
    }

    /**
     * @param array<int, Message> $messages
     *
     * @return array<int, MessageDTO>
     */
    public function createMessageList(array $messages): array
    {
        return array_map(static fn (Message $message) => MessageDTO::fromEntity($message), $messages);
    }

    public function createMessageDto(Message $message): MessageDTO
    {
        return MessageDTO::fromEntity($message);
    }
}
