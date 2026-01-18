<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TelegramIdentity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TelegramIdentity>
 */
class TelegramIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelegramIdentity::class);
    }

    public function findTelegramChatIdByUser(User $user): ?string
    {
        $result = $this->createQueryBuilder('t')
            ->select('t.telegramChatId')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();

        if (!is_array($result)) {
            return null;
        }

        $chatId = $result['telegramChatId'] ?? null;

        return is_string($chatId) && $chatId !== '' ? $chatId : null;
    }
}
