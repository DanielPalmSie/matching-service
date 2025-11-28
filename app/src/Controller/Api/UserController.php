<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class UserController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
    public function createOrUpdate(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !isset($payload['externalId']) || !is_string($payload['externalId'])) {
            return new JsonResponse(['error' => 'Invalid payload: externalId is required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['externalId' => $payload['externalId']]);
        $isNew = false;

        if ($user === null) {
            $user = new User();
            $user->setExternalId($payload['externalId']);
            $user->setCreatedAt(new DateTimeImmutable());
            $isNew = true;
        }

        $user->setDisplayName($payload['displayName'] ?? $user->getDisplayName());
        $user->setCity($payload['city'] ?? $user->getCity());
        $user->setCountry($payload['country'] ?? $user->getCountry());
        $user->setTimezone($payload['timezone'] ?? $user->getTimezone());

        if ($isNew) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'externalId' => $user->getExternalId(),
            'displayName' => $user->getDisplayName(),
            'city' => $user->getCity(),
            'country' => $user->getCountry(),
            'timezone' => $user->getTimezone(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/api/users/{id}', name: 'api_users_get', methods: ['GET'])]
    public function getUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            return new JsonResponse(['error' => 'User not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'externalId' => $user->getExternalId(),
            'displayName' => $user->getDisplayName(),
            'city' => $user->getCity(),
            'country' => $user->getCountry(),
            'timezone' => $user->getTimezone(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ]);
    }
}
