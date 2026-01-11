<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;

class MeService
{
    /**
     * @return array<string, mixed>
     */
    public function buildResponse(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ];
    }
}
