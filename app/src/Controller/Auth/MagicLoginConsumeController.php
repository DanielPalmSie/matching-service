<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\MagicLoginToken;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Service\TelegramLoginNotifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class MagicLoginConsumeController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly TelegramLoginNotifier $telegramLoginNotifier,
        private readonly LoggerInterface $logger,
    ) {
    }

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

        $tokenPrefix = substr($token, 0, 6);
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
            return new JsonResponse(['error' => 'Invalid or expired link'], Response::HTTP_BAD_REQUEST);
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
                    $jwt
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

        return new JsonResponse(['token' => $jwt]);
    }
}
