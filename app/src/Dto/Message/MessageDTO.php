<?php

declare(strict_types=1);

namespace App\Dto\Message;

use App\Entity\Message;

readonly class MessageDTO
{
    public function __construct(
        public int $id,
        public int $chatId,
        public int $senderId,
        public string $content,
        public string $createdAt,
        public bool $isRead,
    ) {
    }

    public static function fromEntity(Message $message): self
    {
        return new self(
            $message->getId(),
            $message->getChat()->getId(),
            $message->getSender()->getId(),
            $message->getContent(),
            $message->getCreatedAt()->format(DATE_ATOM),
            $message->isRead(),
        );
    }
}
