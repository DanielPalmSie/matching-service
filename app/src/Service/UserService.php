<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserEmbeddingSynchronizer $userEmbeddingSynchronizer,
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
        $isNew = $user === null;

        if ($user === null) {
            $user = new User();
            $user->setExternalId($payload['externalId']);
            $user->setCreatedAt(new DateTimeImmutable());
        }

        if (isset($payload['email']) && is_string($payload['email']) && $payload['email'] !== '') {
            $user->setEmail($payload['email']);
        } elseif ($isNew) {
            $user->setEmail($payload['externalId']);
        }

        $password = $payload['password'] ?? null;

        if ($isNew) {
            if (is_string($password) && $password !== '') {
                $hashed = $this->passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashed);
            } else {
                $generatedPassword = bin2hex(random_bytes(16));
                $hashed = $this->passwordHasher->hashPassword($user, $generatedPassword);
                $user->setPassword($hashed);
            }
        } elseif (is_string($password) && $password !== '') {
            $hashed = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashed);
        }

        $user->setDisplayName($payload['displayName'] ?? $user->getDisplayName());
        $user->setCity($payload['city'] ?? $user->getCity());
        $user->setCountry($payload['country'] ?? $user->getCountry());
        $user->setTimezone($payload['timezone'] ?? $user->getTimezone());

        if ($isNew) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        $this->userEmbeddingSynchronizer->sync($user);

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
