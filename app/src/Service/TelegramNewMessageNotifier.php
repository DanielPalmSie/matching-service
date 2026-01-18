<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Message;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class TelegramNewMessageNotifier
{
    private const TEXT_PREVIEW_LIMIT = 160;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $botInternalUrl,
        private readonly float $timeoutSeconds,
    ) {
    }

    public function notifyNewMessage(Message $message, User $recipient, string $telegramChatId): void
    {
        $url = rtrim($this->botInternalUrl, '/') . '/internal/telegram/notify-new-message';
        $payload = $this->buildPayload($message, $telegramChatId);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => $this->timeoutSeconds,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 300) {
                $this->logger->error('Telegram bot responded with non-success status for new message', [
                    'statusCode' => $statusCode,
                    'telegramChatId' => $telegramChatId,
                    'chatId' => $message->getChat()->getId(),
                    'messageId' => $message->getId(),
                    'recipientUserId' => $recipient->getId(),
                ]);

                return;
            }

            $this->logger->info('Telegram bot notified about new message', [
                'statusCode' => $statusCode,
                'telegramChatId' => $telegramChatId,
                'chatId' => $message->getChat()->getId(),
                'messageId' => $message->getId(),
                'recipientUserId' => $recipient->getId(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to notify Telegram bot about new message', [
                'exception' => $exception,
                'telegramChatId' => $telegramChatId,
                'chatId' => $message->getChat()->getId(),
                'messageId' => $message->getId(),
                'recipientUserId' => $recipient->getId(),
            ]);
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function buildPayload(Message $message, string $telegramChatId): array
    {
        $sender = $message->getSender();
        $senderDisplayName = $sender->getDisplayName();
        if ($senderDisplayName === null || $senderDisplayName === '') {
            $senderId = $sender->getId();
            $senderDisplayName = $senderId !== null ? sprintf('User %d', $senderId) : 'User';
        }

        $content = trim($message->getContent());
        if ($content === '') {
            $content = 'New message';
        }

        if (mb_strlen($content) > self::TEXT_PREVIEW_LIMIT) {
            $content = mb_substr($content, 0, self::TEXT_PREVIEW_LIMIT);
        }

        return [
            'telegramChatId' => (string) $telegramChatId,
            'chatId' => $message->getChat()->getId(),
            'messageId' => $message->getId(),
            'senderUserId' => $sender->getId(),
            'senderDisplayName' => $senderDisplayName,
            'textPreview' => $content,
            'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
