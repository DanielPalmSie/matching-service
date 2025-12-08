<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MagicLinkService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class MagicLinkController extends AbstractController
{
    public function __construct(
        private readonly MagicLinkService $magicLinkService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/api/auth/magic-link/request', name: 'auth_magic_link_request', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        $email = $payload['email'] ?? null;
        if (!is_string($email) || $email === '') {
            return new JsonResponse(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        $name = $payload['name'] ?? '';
        $name = is_string($name) ? $name : '';

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user === null) {
            $user = new User();
            $user->setEmail($email);
            $user->setExternalId($email);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(8))));
            $user->setDisplayName($name);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        try {
            $this->magicLinkService->createAndSend($user);
        } catch (TransportExceptionInterface $exception) {
            return new JsonResponse(
                ['error' => 'Failed to send magic login link'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
