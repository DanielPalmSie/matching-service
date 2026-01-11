<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\MagicLoginConsumeService;
use App\Service\Exception\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MagicLoginConsumeController
{
    public function __construct(
        private readonly MagicLoginConsumeService $magicLoginConsumeService,
    ) {
    }

    #[Route('/api/auth/magic-link/verify', name: 'auth_magic_login_consume', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $jwt = $this->magicLoginConsumeService->consume($request);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['token' => $jwt]);
    }
}
