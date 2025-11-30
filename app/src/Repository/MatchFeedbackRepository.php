<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MatchFeedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchFeedback>
 */
class MatchFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchFeedback::class);
    }

    /**
     * @return MatchFeedback[]
     */
    public function findCommentsForPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('mf')
            ->andWhere('mf.comment IS NOT NULL')
            ->andWhere('mf.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('mf.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
