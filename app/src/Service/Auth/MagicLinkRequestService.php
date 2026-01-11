<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Exception\ValidationException;
use App\Service\Http\JsonPayloadDecoder;
use App\Service\MagicLinkService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MagicLinkRequestService
{
    public function __construct(
        private readonly MagicLinkService $magicLinkService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $payloadDecoder,
    ) {
    }

    public function requestLink(Request $request): void
    {
        $payload = $this->payloadDecoder->decode($request, 'Email is required');

        $email = $payload['email'] ?? null;
        if (!is_string($email) || $email === '') {
            throw new ValidationException('Email is required');
        }

        $name = $payload['name'] ?? '';
        $name = is_string($name) ? $name : '';

        $telegramChatId = $payload['telegram_chat_id'] ?? null;
        if ($telegramChatId !== null) {
            if (!is_int($telegramChatId) && !is_string($telegramChatId)) {
                throw new ValidationException('Invalid telegram_chat_id');
            }

            $telegramChatId = trim((string) $telegramChatId);

            if ($telegramChatId === '' || !preg_match('/^-?\d+$/', $telegramChatId)) {
                throw new ValidationException('Invalid telegram_chat_id');
            }

            $telegramChatId = (int) $telegramChatId;
        }

        $this->logger->info('magicLink.request', [
            'email' => $email,
            'telegramChatId' => $telegramChatId,
        ]);

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

        $this->magicLinkService->createAndSend($user, $telegramChatId);
    }
}
