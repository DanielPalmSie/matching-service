<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chat;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chat>
 */
class ChatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chat::class);
    }

    public function findExistingChatBetweenUsers(User $a, User $b): ?Chat
    {
        $qb = $this->createQueryBuilder('c');

        return $qb
            ->innerJoin('c.participants', 'p')
            ->where($qb->expr()->in('p', ':users'))
            ->andWhere('SIZE(c.participants) = 2')
            ->groupBy('c.id')
            ->having('COUNT(DISTINCT p.id) = 2')
            ->setParameter('users', [$a, $b])
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, Chat>
     */
    public function findChatsForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->leftJoin('c.messages', 'm')
            ->addSelect('MAX(m.createdAt) AS HIDDEN lastMessageAt')
            ->where('p = :user')
            ->groupBy('c.id')
            ->orderBy('lastMessageAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
