<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createOrUpdate(array $payload): array
    {
        $this->assertExternalId($payload['externalId'] ?? null);

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

        return $this->mapUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserData(int $id): array
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundException('User not found.');
        }

        return $this->mapUser($user);
    }

    private function assertExternalId(mixed $externalId): void
    {
        if (!is_string($externalId) || $externalId === '') {
            throw new ValidationException('Invalid payload: externalId is required.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'externalId' => $user->getExternalId(),
            'displayName' => $user->getDisplayName(),
            'city' => $user->getCity(),
            'country' => $user->getCountry(),
            'timezone' => $user->getTimezone(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
