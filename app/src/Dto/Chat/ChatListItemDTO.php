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
     * @param array{type: string, id: int}|null $context
     */
    public function __construct(
        public int $id,
        public array $participants,
        public ?MessageDTO $lastMessage,
        public int $unreadCount,
        public string $title,
        public ?string $subtitle,
        public ?array $context,
    ) {
    }

    /**
     * @param array{type: string, id: int}|null $context
     */
    public static function fromChat(
        Chat $chat,
        ?Message $lastMessage,
        int $unreadCount,
        string $title,
        ?string $subtitle,
        ?array $context,
    ): self {
        $participants = array_map(
            static fn (User $user) => new ChatParticipantDTO($user->getId(), $user->getDisplayName()),
            $chat->getParticipants()->toArray(),
        );

        return new self(
            $chat->getId(),
            $participants,
            $lastMessage !== null ? MessageDTO::fromEntity($lastMessage) : null,
            $unreadCount,
            $title,
            $subtitle,
            $context,
        );
    }
}
