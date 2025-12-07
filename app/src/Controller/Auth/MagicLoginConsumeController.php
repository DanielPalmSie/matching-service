<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\MagicLoginToken;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class MagicLoginConsumeController
{
    private string $frontendBaseUrl;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        #[Autowire(param: 'app.frontend_base_url')] string $frontendBaseUrl,
    ) {
        $this->frontendBaseUrl = rtrim($frontendBaseUrl !== '' ? $frontendBaseUrl : 'https://matchinghub.work', '/');
    }

    #[Route('/auth/magic-login/{token}', name: 'auth_magic_login_consume', methods: ['GET'])]
    public function __invoke(string $token): RedirectResponse|JsonResponse
    {
        $magicToken = $this->entityManager->getRepository(MagicLoginToken::class)->findOneBy(['token' => $token]);

        if (!$magicToken instanceof MagicLoginToken || !$magicToken->isValid()) {
            return new JsonResponse(['error' => 'Invalid or expired link'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $magicToken->setUsedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $jwt = $this->jwtTokenManager->create($magicToken->getUser());
        $redirectUrl = sprintf('%s/app?token=%s', $this->frontendBaseUrl, urlencode($jwt));

        return new RedirectResponse($redirectUrl);
    }
}
