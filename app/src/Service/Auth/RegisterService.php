<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\EmailConfirmationToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Exception\ConflictException;
use App\Service\Exception\ValidationException;
use App\Service\RegistrationEmailService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegisterService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly RegistrationEmailService $registrationEmailService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function register(array $payload): void
    {
        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;

        if (!is_string($email) || $email === '' || !is_string($password) || $password === '') {
            throw new ValidationException('Email and password are required.');
        }

        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            throw new ConflictException('User already exists.');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setExternalId($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsVerified(false);

        $confirmationToken = new EmailConfirmationToken($user, new DateTimeImmutable('+48 hours'));

        $this->entityManager->persist($user);
        $this->entityManager->persist($confirmationToken);
        $this->entityManager->flush();

        $this->registrationEmailService->sendConfirmationEmail($user, $confirmationToken);
    }
}
