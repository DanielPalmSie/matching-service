<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chat;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return array<int, Message>
     */
    public function findMessagesForChat(Chat $chat, int $offset, int $limit): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.chat = :chat')
            ->orderBy('m.createdAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter('chat', $chat)
            ->getQuery()
            ->getResult();
    }

    public function findLastMessageForChat(Chat $chat): ?Message
    {
        return $this->createQueryBuilder('m')
            ->where('m.chat = :chat')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->setParameter('chat', $chat)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
