<?php

declare(strict_types=1);

namespace App\Dto\Message;

class CreateMessageDTO
{
    public function __construct(public readonly string $content)
    {
    }
}
