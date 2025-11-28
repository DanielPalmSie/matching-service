<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Request;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Request>
 */
class RequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Request::class);
    }

    /**
     * @return Request[]
     */
    public function findActiveCandidates(Request $source): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.type = :type')
            ->andWhere('r.id != :id')
            ->setParameter('status', 'active')
            ->setParameter('type', $source->getType())
            ->setParameter('id', $source->getId());

        if ($source->getCity() !== null) {
            $qb->andWhere('r.city = :city')
                ->setParameter('city', $source->getCity());
        } elseif ($source->getCountry() !== null) {
            $qb->andWhere('r.country = :country')
                ->setParameter('country', $source->getCountry());
        }

        return $qb->getQuery()->getResult();
    }
}
