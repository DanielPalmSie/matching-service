<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\EmailConfirmationToken;
use App\Repository\EmailConfirmationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConfirmEmailController
{
    private string $frontendBaseUrl;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailConfirmationTokenRepository $tokenRepository,
        #[Autowire(param: 'app.frontend_base_url')] string $frontendBaseUrl,
    ) {
        $this->frontendBaseUrl = rtrim($frontendBaseUrl !== '' ? $frontendBaseUrl : 'https://matchinghub.work', '/');
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
        $confirmationToken = $this->tokenRepository->findOneBy(['token' => $token]);

        if (!$confirmationToken instanceof EmailConfirmationToken || !$confirmationToken->isValid()) {
            return new JsonResponse(['error' => 'Invalid or expired confirmation token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $confirmationToken->getUser();
        $user->markEmailVerified();

        $this->entityManager->remove($confirmationToken);
        $this->entityManager->flush();

        $redirectUrl = sprintf('%s/auth/email-confirmed?status=success', $this->frontendBaseUrl);

        return new RedirectResponse($redirectUrl);
    }
}
