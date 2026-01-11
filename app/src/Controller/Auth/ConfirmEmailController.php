<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\ConfirmEmailService;
use App\Service\Exception\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConfirmEmailController
{
    public function __construct(
        private readonly ConfirmEmailService $confirmEmailService,
    ) {
    }

    #[Route('/api/auth/confirm-email/{token}', name: 'auth_confirm_email', methods: ['GET'])]
    #[OA\Get(
        path: '/api/auth/confirm-email/{token}',
        summary: 'Confirm a newly registered email address',
        description: 'Marks the user as verified and invalidates the confirmation token.',
        tags: ['Auth'],
        parameters: [new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: Response::HTTP_FOUND, description: 'Redirects to the frontend after successful confirmation.'),
            new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid or expired confirmation token.'),
        ],
    )]
    public function __invoke(string $token): RedirectResponse|JsonResponse
    {
        try {
            $redirectUrl = $this->confirmEmailService->confirm($token);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new RedirectResponse($redirectUrl);
    }
}
