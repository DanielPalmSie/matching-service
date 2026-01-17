<?php

declare(strict_types=1);

namespace App\Service\Chat;

use App\Entity\Chat;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ChatRepository;
use App\Service\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class ChatService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatRepository $chatRepository,
        private readonly HubInterface $hub,
        private readonly ChatDtoFactory $chatDtoFactory,
    ) {
    }

    public function createOrGetChat(
        User $userA,
        User $userB,
        ?string $originType = null,
        ?int $originId = null,
        ?string $contextTitle = null,
        ?string $contextSubtitle = null,
        ?string $contextSource = null,
    ): Chat {
        if (($originType === null) !== ($originId === null)) {
            throw new ValidationException('Origin type and id must be provided together.');
        }

        $normalizedContextTitle = $this->normalizeContextValue($contextTitle, 255);
        $normalizedContextSubtitle = $this->normalizeContextValue($contextSubtitle, 255);
        $normalizedContextSource = $this->normalizeContextValue($contextSource, 32);

        $pairKey = $this->buildPairKey($userA, $userB);

        if ($originType !== null && $originId !== null) {
            $existing = $this->chatRepository->findChatByPairKeyAndOrigin($pairKey, $originType, $originId);
            if ($existing !== null) {
                if ($this->applyContext($existing, $normalizedContextTitle, $normalizedContextSubtitle, $normalizedContextSource)) {
                    $this->entityManager->flush();
                }
                return $existing;
            }
        } else {
            $existing = $this->chatRepository->findExistingChatBetweenUsers($userA, $userB);
            if ($existing !== null) {
                if ($this->applyContext($existing, $normalizedContextTitle, $normalizedContextSubtitle, $normalizedContextSource)) {
                    $this->entityManager->flush();
                }
                return $existing;
            }
        }

        $chat = new Chat();
        $chat->setPairKey($pairKey);
        $chat->setOriginType($originType);
        $chat->setOriginId($originId);
        $this->applyContext($chat, $normalizedContextTitle, $normalizedContextSubtitle, $normalizedContextSource);
        $chat->addParticipant($userA);
        $chat->addParticipant($userB);

        $this->entityManager->persist($chat);
        $this->entityManager->flush();

        return $chat;
    }

    public function sendMessage(Chat $chat, User $sender, string $content): Message
    {
        $this->assertParticipant($chat, $sender);
        $normalizedContent = trim($content);
        if ($normalizedContent === '') {
            throw new ValidationException('Message content cannot be empty.');
        }

        $message = new Message();
        $message->setChat($chat);
        $message->setSender($sender);
        $message->setContent($normalizedContent);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->publishMessage($message);

        return $message;
    }

    public function markMessageAsRead(Message $message, User $currentUser): void
    {
        $this->assertParticipant($message->getChat(), $currentUser);
        if ($message->getSender()->getId() === $currentUser->getId()) {
            throw new ValidationException('Sender cannot mark own message as read for other participants.');
        }

        if ($message->isRead()) {
            return;
        }

        $message->setIsRead(true);
        $this->entityManager->flush();

        $payload = json_encode([
            'type' => 'read',
            'messageId' => $message->getId(),
            'userId' => $currentUser->getId(),
        ], JSON_THROW_ON_ERROR);

        $update = new Update(
            sprintf('/chats/%d', $message->getChat()->getId()),
            $payload,
            private: true
        );

        $this->hub->publish($update);
    }

    private function publishMessage(Message $message): void
    {
        $messageDto = $this->chatDtoFactory->createMessageDto($message);
        $payload = json_encode($messageDto, JSON_THROW_ON_ERROR);

        $update = new Update(
            sprintf('/chats/%d', $message->getChat()->getId()),
            $payload,
            private: true
        );

        $this->hub->publish($update);
    }

    private function assertParticipant(Chat $chat, User $user): void
    {
        if (!$chat->getParticipants()->contains($user)) {
            throw new ValidationException('User is not a participant of the chat.');
        }
    }

    private function buildPairKey(User $userA, User $userB): string
    {
        $userAId = $userA->getId();
        $userBId = $userB->getId();
        if ($userAId === null || $userBId === null) {
            throw new ValidationException('Users must be persisted before starting a chat.');
        }

        $minId = min($userAId, $userBId);
        $maxId = max($userAId, $userBId);

        return sprintf('%d:%d', $minId, $maxId);
    }

    private function applyContext(
        Chat $chat,
        ?string $contextTitle,
        ?string $contextSubtitle,
        ?string $contextSource,
    ): bool {
        $updated = false;

        if ($contextTitle !== null && $chat->getContextTitle() === null) {
            $chat->setContextTitle($contextTitle);
            $updated = true;
        }

        if ($contextSubtitle !== null && $chat->getContextSubtitle() === null) {
            $chat->setContextSubtitle($contextSubtitle);
            $updated = true;
        }

        if ($contextSource !== null && $chat->getContextSource() === null) {
            $chat->setContextSource($contextSource);
            $updated = true;
        }

        return $updated;
    }

    private function normalizeContextValue(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            $normalized = mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }
}
