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

    public function createOrGetChat(User $userA, User $userB): Chat
    {
        $existing = $this->chatRepository->findExistingChatBetweenUsers($userA, $userB);
        if ($existing !== null) {
            return $existing;
        }

        $chat = new Chat();
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
}
