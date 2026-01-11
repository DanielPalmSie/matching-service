<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\EmailConfirmationToken;
use App\Repository\EmailConfirmationTokenRepository;
use App\Service\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ConfirmEmailService
{
    private string $frontendBaseUrl;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailConfirmationTokenRepository $tokenRepository,
        #[Autowire(param: 'app.frontend_base_url')] string $frontendBaseUrl,
    ) {
        $this->frontendBaseUrl = rtrim($frontendBaseUrl !== '' ? $frontendBaseUrl : 'https://matchinghub.work', '/');
    }

    public function confirm(string $token): string
    {
        $confirmationToken = $this->tokenRepository->findOneBy(['token' => $token]);

        if (!$confirmationToken instanceof EmailConfirmationToken || !$confirmationToken->isValid()) {
            throw new ValidationException('Invalid or expired confirmation token');
        }

        $user = $confirmationToken->getUser();
        $user->markEmailVerified();

        $this->entityManager->remove($confirmationToken);
        $this->entityManager->flush();

        return sprintf('%s/auth/email-confirmed?status=success', $this->frontendBaseUrl);
    }
}
