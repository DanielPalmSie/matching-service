<?php

declare(strict_types=1);

namespace App\Dto\Chat;

readonly class ChatParticipantDTO
{
    public function __construct(
        public int $id,
        public ?string $displayName,
    ) {
    }
}
