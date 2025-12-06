<?php

declare(strict_types=1);

namespace App\Dto\Chat;

use App\Dto\Message\MessageDTO;
use App\Entity\Chat;
use App\Entity\Message;
use App\Entity\User;

readonly class ChatListItemDTO
{
    /**
     * @param array<int, ChatParticipantDTO> $participants
     */
    public function __construct(
        public int $id,
        public array $participants,
        public ?MessageDTO $lastMessage,
        public int $unreadCount = 0,
    ) {
    }

    public static function fromChat(Chat $chat, ?Message $lastMessage, int $unreadCount = 0): self
    {
        $participants = array_map(
            static fn (User $user) => new ChatParticipantDTO($user->getId(), $user->getDisplayName()),
            $chat->getParticipants()->toArray(),
        );

        return new self(
            $chat->getId(),
            $participants,
            $lastMessage !== null ? MessageDTO::fromEntity($lastMessage) : null,
            $unreadCount,
        );
    }
}
