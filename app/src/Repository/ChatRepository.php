<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chat;
use App\Entity\Request;
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

    public function findChatByPairKeyAndOrigin(string $pairKey, string $originType, int $originId): ?Chat
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.pairKey = :pairKey')
            ->andWhere('c.originType = :originType')
            ->andWhere('c.originId = :originId')
            ->setParameter('pairKey', $pairKey)
            ->setParameter('originType', $originType)
            ->setParameter('originId', $originId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, array{chat: Chat, originRequest: ?Request}>
     */
    public function findChatsForUser(User $user): array
    {
        $results = $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->leftJoin('c.messages', 'm')
            ->leftJoin(Request::class, 'r', 'WITH', 'c.originType = :originType AND c.originId = r.id')
            ->addSelect('r')
            ->addSelect('MAX(m.createdAt) AS HIDDEN lastMessageAt')
            ->where('p = :user')
            ->groupBy('c.id, r.id')
            ->orderBy('lastMessageAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->setParameter('originType', 'request')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(array_map(
            static function (mixed $row): ?array {
                if ($row instanceof Chat) {
                    return ['chat' => $row, 'originRequest' => null];
                }

                if (is_array($row)) {
                    $chat = $row['c'] ?? $row[0] ?? null;
                    if (!$chat instanceof Chat) {
                        return null;
                    }

                    $request = $row['r'] ?? $row[1] ?? null;

                    return ['chat' => $chat, 'originRequest' => $request instanceof Request ? $request : null];
                }

                return null;
            },
            $results
        )));
    }
}
