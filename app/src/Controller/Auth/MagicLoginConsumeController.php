<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\MagicLoginToken;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MagicLoginConsumeController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
    ) {}

    #[Route('/api/auth/magic-link/verify', name: 'auth_magic_login_consume', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Token is required'], Response::HTTP_BAD_REQUEST);
        }

        $token = $payload['token'] ?? null;
        if (!is_string($token) || $token === '') {
            return new JsonResponse(['error' => 'Token is required'], Response::HTTP_BAD_REQUEST);
        }

        $magicToken = $this->entityManager->getRepository(MagicLoginToken::class)->findOneBy(['token' => $token]);

        if (!$magicToken instanceof MagicLoginToken || !$magicToken->isValid()) {
            return new JsonResponse(['error' => 'Invalid or expired link'], Response::HTTP_BAD_REQUEST);
        }

        $magicToken->setUsedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $jwt = $this->jwtTokenManager->create($magicToken->getUser());

        return new JsonResponse(['token' => $jwt]);
    }
}
