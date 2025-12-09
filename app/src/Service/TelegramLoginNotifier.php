<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class TelegramLoginNotifier
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyUserLoggedIn(User $user, int|string $telegramChatId, ?string $jwt = null): void
    {
        $chatId = (string) $telegramChatId;
        $topic = sprintf('/tg/login/%s', $chatId);

        $payload = [
            'type' => 'user_logged_in',
            'chat_id' => $chatId,
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ];

        if ($jwt !== null) {
            $payload['jwt'] = $jwt;
        }

        try {
            $this->logger->info('Publishing Telegram login event to Mercure', [
                'topic' => $topic,
                'chat_id' => $chatId,
                'user_id' => $user->getId(),
            ]);

            $update = new Update($topic, json_encode($payload, JSON_THROW_ON_ERROR));
            $this->hub->publish($update);

            $this->logger->info('Telegram login event published', [
                'topic' => $topic,
                'chat_id' => $chatId,
                'user_id' => $user->getId(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to publish Telegram login event', [
                'exception' => $exception,
                'chat_id' => $chatId,
                'user_id' => $user->getId(),
            ]);
        }
    }
}
