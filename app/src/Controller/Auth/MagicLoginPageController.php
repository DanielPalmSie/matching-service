<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\MagicLoginToken;
use App\Repository\MagicLoginTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MagicLoginPageController extends AbstractController
{
    public function __construct(
        private readonly MagicLoginTokenRepository $magicLoginTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
    ) {
    }

    #[Route('/auth/magic-login/{token}', name: 'magic_login_page', methods: ['GET'])]
    public function __invoke(string $token): Response
    {
        $magicToken = $this->magicLoginTokenRepository->findOneBy(['token' => $token]);

        if (!$magicToken instanceof MagicLoginToken || !$magicToken->isValid()) {
            return $this->render('auth/magic_login_error.html.twig');
        }

        $magicToken->setUsedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $jwt = $this->jwtTokenManager->create($magicToken->getUser());

        return $this->render('auth/magic_login_success.html.twig', [
            'jwt' => $jwt,
        ]);
    }
}
