<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\MagicLoginToken;
use App\Service\Exception\ValidationException;
use App\Service\Http\JsonPayloadDecoder;
use App\Service\TelegramLoginNotifier;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class MagicLoginConsumeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly TelegramLoginNotifier $telegramLoginNotifier,
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $payloadDecoder,
    ) {
    }

    public function consume(Request $request): string
    {
        $payload = $this->payloadDecoder->decode($request, 'Token is required');

        $token = $payload['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new ValidationException('Token is required');
        }

        $magicToken = $this->entityManager->getRepository(MagicLoginToken::class)->findOneBy(['token' => $token]);

        $tokenPrefix = substr($token, 0, 8);
        $userId = $magicToken instanceof MagicLoginToken ? $magicToken->getUser()->getId() : null;
        $email = $magicToken instanceof MagicLoginToken ? $magicToken->getUser()->getEmail() : null;
        $telegramChatId = $magicToken instanceof MagicLoginToken ? $magicToken->getTelegramChatId() : null;

        $this->logger->info('magicLink.consume', [
            'tokenPrefix' => $tokenPrefix,
            'userId' => $userId,
            'email' => $email,
            'telegramChatId' => $telegramChatId,
            'ip' => $request->getClientIp(),
            'userAgent' => $request->headers->get('User-Agent'),
        ]);

        if (!$magicToken instanceof MagicLoginToken || !$magicToken->isValid()) {
            throw new ValidationException('Invalid or expired link');
        }

        $this->logger->info('Magic login token consumed', [
            'magic_login_token_id' => $magicToken->getId(),
            'user_id' => $magicToken->getUser()->getId(),
            'telegram_chat_id' => $magicToken->getTelegramChatId(),
        ]);

        $magicToken->setUsedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $jwt = $this->jwtTokenManager->create($magicToken->getUser());

        if ($magicToken->getTelegramChatId() !== null) {
            try {
                $this->telegramLoginNotifier->notifyUserLoggedIn(
                    $magicToken->getUser(),
                    $magicToken->getTelegramChatId(),
                    $jwt,
                    $tokenPrefix
                );

                $this->logger->info('Telegram login notifier dispatched', [
                    'magic_login_token_id' => $magicToken->getId(),
                    'chat_id' => $magicToken->getTelegramChatId(),
                ]);
            } catch (Throwable $exception) {
                $this->logger->error('Failed to dispatch Telegram login notifier', [
                    'exception' => $exception,
                    'magic_login_token_id' => $magicToken->getId(),
                    'chat_id' => $magicToken->getTelegramChatId(),
                ]);
            }
        } else {
            $this->logger->warning('Magic login token used without a telegram chat id', [
                'magic_login_token_id' => $magicToken->getId(),
                'user_id' => $magicToken->getUser()->getId(),
            ]);
        }

        return $jwt;
    }
}
