<?php

declare(strict_types=1);

namespace App\Service\Chat;

use App\Dto\Chat\ChatListItemDTO;
use App\Dto\Message\MessageDTO;
use App\Entity\Chat;
use App\Entity\Message;
use App\Entity\Request;
use App\Entity\User;
use App\Repository\MessageRepository;

class ChatDtoFactory
{
    public function __construct(private readonly MessageRepository $messageRepository)
    {
    }

    public function createChatListItem(Chat $chat, ?User $currentUser = null, ?Request $originRequest = null): ChatListItemDTO
    {
        $lastMessage = $this->messageRepository->findLastMessageForChat($chat);
        $unreadCount = $currentUser !== null ? $this->messageRepository->countUnreadMessagesForChatAndUser($chat, $currentUser) : 0;
        [$title, $subtitle, $context] = $this->resolveChatContext($chat, $currentUser, $originRequest);

        return ChatListItemDTO::fromChat($chat, $lastMessage, $unreadCount, $title, $subtitle, $context);
    }

    /**
     * @param array<int, Message> $messages
     *
     * @return array<int, MessageDTO>
     */
    public function createMessageList(array $messages): array
    {
        return array_map(static fn (Message $message) => MessageDTO::fromEntity($message), $messages);
    }

    public function createMessageDto(Message $message): MessageDTO
    {
        return MessageDTO::fromEntity($message);
    }

    /**
     * @return array{0: string, 1: ?string, 2: array{type: string, id: int}|null}
     */
    private function resolveChatContext(Chat $chat, ?User $currentUser, ?Request $originRequest): array
    {
        $contextTitle = $chat->getContextTitle();
        $contextSubtitle = $chat->getContextSubtitle();
        $fallbackTitle = $this->resolveParticipantTitle($chat, $currentUser);
        $requestTitle = null;
        $requestSubtitle = null;
        $context = null;

        if ($chat->getOriginType() === 'request') {
            $request = $originRequest;
            $requestTitle = $request instanceof Request
                ? $this->truncateTitle($request->getRawText())
                : $fallbackTitle;

            $requestSubtitle = $request instanceof Request
                ? $this->formatLocation($request->getCity(), $request->getCountry())
                : null;

            $context = $chat->getOriginId() !== null
                ? ['type' => 'request', 'id' => $chat->getOriginId()]
                : null;
        }

        return [
            $contextTitle ?? $requestTitle ?? $fallbackTitle,
            $contextSubtitle ?? $requestSubtitle,
            $context,
        ];
    }

    private function resolveParticipantTitle(Chat $chat, ?User $currentUser): string
    {
        if ($currentUser === null) {
            return 'Chat';
        }

        foreach ($chat->getParticipants() as $participant) {
            if ($participant->getId() !== $currentUser->getId()) {
                return $participant->getDisplayName() ?? 'Chat';
            }
        }

        return 'Chat';
    }

    private function truncateTitle(string $rawText): string
    {
        $normalized = trim($rawText);
        if ($normalized === '') {
            return '';
        }

        $length = mb_strlen($normalized);
        if ($length <= 60) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, 60)) . '…';
    }

    private function formatLocation(?string $city, ?string $country): ?string
    {
        $parts = array_filter([$city, $country], static fn (?string $value) => $value !== null && $value !== '');
        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
