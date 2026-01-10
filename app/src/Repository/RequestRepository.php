<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Request;
use App\Entity\User;
use Doctrine\DBAL\ParameterType;
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

    /**
     * @return Request[]
     */
    public function findActiveByOwner(User $owner, int $offset = 0, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.owner = :owner')
            ->setParameter('status', 'active')
            ->setParameter('owner', $owner)
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset);

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLatestActiveByOwner(User $owner): ?Request
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.owner = :owner')
            ->setParameter('status', 'active')
            ->setParameter('owner', $owner)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Executes a pgvector-powered similarity search for requests owned by the nearest users.
     *
     * @param array<int, float> $embedding
     *
     * @return array<int, array{id: int, distance: float}>
     */
    public function findNearestByEmbedding(Request $source, array $embedding, int $limit = 20): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $conditions = ['r.status = :status', 'r.type = :type', 'r.id != :id'];
        $parameters = [
            'status' => 'active',
            'type' => $source->getType(),
            'id' => $source->getId(),
            'query_vector' => $this->formatVector($embedding),
            'limit' => $limit,
        ];
        $types = [
            'status' => ParameterType::STRING,
            'type' => ParameterType::STRING,
            'id' => ParameterType::INTEGER,
            'query_vector' => ParameterType::STRING,
            'limit' => ParameterType::INTEGER,
        ];
        $ownerId = $source->getOwner()->getId();
        if ($ownerId !== null) {
            $conditions[] = 'r.owner_id != :owner_id';
            $parameters['owner_id'] = $ownerId;
            $types['owner_id'] = ParameterType::INTEGER;
        }

        if ($source->getCity() !== null) {
            $conditions[] = 'r.city = :city';
            $parameters['city'] = $source->getCity();
            $types['city'] = ParameterType::STRING;
        } elseif ($source->getCountry() !== null) {
            $conditions[] = 'r.country = :country';
            $parameters['country'] = $source->getCountry();
            $types['country'] = ParameterType::STRING;
        }

        $sql = sprintf(
            'SELECT r.id, (ue.embedding <-> :query_vector) AS distance
             FROM user_embeddings ue
             INNER JOIN request r ON r.owner_id = ue.user_id
             WHERE %s
             ORDER BY ue.embedding <-> :query_vector
             LIMIT :limit',
            implode(' AND ', $conditions),
        );

        $rows = $connection->executeQuery($sql, $parameters, $types)->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'distance' => (float) $row['distance'],
            ],
            $rows,
        );
    }

    /**
     * @param array<int, float> $embedding
     */
    private function formatVector(array $embedding): string
    {
        return '[' . implode(',', array_map(static fn ($value) => sprintf('%.12f', $value), $embedding)) . ']';
    }
}
