<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\MagicLoginToken;
use App\Repository\MagicLoginTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use App\Entity\TelegramIdentity;
use App\Repository\TelegramIdentityRepository;

class MagicLoginPageService
{
    public function __construct(
        private readonly MagicLoginTokenRepository $magicLoginTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly HubInterface $hub,
        private readonly TelegramIdentityRepository $telegramIdentityRepository,
        #[Autowire(service: 'monolog.logger.magic_login')]
        private readonly LoggerInterface $magicLoginLogger,
    ) {
    }

    public function handle(string $token): bool
    {
        $magicToken = $this->magicLoginTokenRepository->findOneBy(['token' => $token]);

        if (!$magicToken instanceof MagicLoginToken || !$magicToken->isValid()) {
            return false;
        }

        $magicToken->setUsedAt(new DateTimeImmutable());

        $telegramChatId = $magicToken->getTelegramChatId();
        if ($telegramChatId !== null) {
            $user = $magicToken->getUser();
            $identity = $this->telegramIdentityRepository->findOneBy(['user' => $user]);

            if (!$identity instanceof TelegramIdentity) {
                $identity = new TelegramIdentity($user);
                $this->entityManager->persist($identity);
            }

            $identity->setTelegramChatId((string) $telegramChatId);
        }

        $this->entityManager->flush();

        $jwt = $this->jwtTokenManager->create($magicToken->getUser());

        $telegramChatId = $magicToken->getTelegramChatId();

        if ($telegramChatId !== null) {
            $topic = sprintf('/tg/login/%s', (string) $telegramChatId);
            $payload = [
                'type' => 'login_success',
                'chat_id' => (string) $telegramChatId,
                'jwt' => $jwt,
            ];
            $payloadKeys = array_keys($payload);

            $this->magicLoginLogger->info('mercure.publish', [
                'topic' => $topic,
                'telegramChatId' => (string) $telegramChatId,
                'type' => $payload['type'],
                'hasJwt' => true,
            ]);

            $this->magicLoginLogger->info('mercure.publish_diag', [
                'flow' => 'magic_link',
                'source' => self::class,
                'topic' => $topic,
                'event_type' => $payload['type'],
                'telegramUserId' => $telegramChatId,
                'chat_id' => $telegramChatId,
                'token_prefix' => substr($token, 0, 8),
                'user_id' => $magicToken->getUser()->getId(),
                'email' => $magicToken->getUser()->getEmail(),
                'payload_keys' => $payloadKeys,
            ]);

            $this->hub->publish(new Update($topic, json_encode($payload, JSON_THROW_ON_ERROR)));

            $this->magicLoginLogger->info('Sent Mercure login event for Telegram', [
                'chat_id' => (string) $telegramChatId,
            ]);
        }

        return true;
    }
}
