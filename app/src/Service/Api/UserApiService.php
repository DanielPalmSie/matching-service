<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Service\Http\JsonPayloadDecoder;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\Request;

class UserApiService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly JsonPayloadDecoder $payloadDecoder,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function createOrUpdate(Request $request): array
    {
        $payload = $this->payloadDecoder->decode($request, 'Invalid payload: externalId is required.');

        return $this->userService->createOrUpdate($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUser(int $id): array
    {
        return $this->userService->getUserData($id);
    }
}
