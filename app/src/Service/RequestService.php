<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Request as RequestEntity;
use App\Repository\RequestRepository;
use App\Repository\UserRepository;
use App\Service\Embedding\EmbeddingClientInterface;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use App\Service\Matching\MatchingEngineInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class RequestService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestRepository $requestRepository,
        private readonly UserRepository $userRepository,
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly MatchingEngineInterface $matchingEngine
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createRequest(array $payload): array
    {
        $this->assertCreatePayload($payload);

        $ownerId = (int) $payload['ownerId'];
        $owner = $this->userRepository->find($ownerId);
        if ($owner === null) {
            throw new NotFoundException('Owner not found.');
        }

        $embedding = $this->embeddingClient->embed($payload['rawText']);

        $requestEntity = new RequestEntity();
        $requestEntity->setOwner($owner);
        $requestEntity->setRawText($payload['rawText']);
        $requestEntity->setType($payload['type']);
        $requestEntity->setCity($payload['city'] ?? null);
        $requestEntity->setCountry($payload['country'] ?? null);
        $requestEntity->setStatus('active');
        $requestEntity->setCreatedAt(new DateTimeImmutable());
        $requestEntity->setEmbedding($embedding);

        $this->entityManager->persist($requestEntity);
        $this->entityManager->flush();

        return $this->mapRequest($requestEntity);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestData(int $id): array
    {
        $requestEntity = $this->requestRepository->find($id);
        if ($requestEntity === null) {
            throw new NotFoundException('Request not found.');
        }

        return $this->mapRequest($requestEntity);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMatchesData(int $id, int $limit): array
    {
        $requestEntity = $this->requestRepository->find($id);
        if ($requestEntity === null) {
            throw new NotFoundException('Request not found.');
        }

        $normalizedLimit = $this->normalizeLimit($limit);
        $matches = $this->matchingEngine->findMatches($requestEntity, $normalizedLimit);

        return array_map(fn (RequestEntity $match) => $this->mapRequest($match, false), $matches);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertCreatePayload(array $payload): void
    {
        if (!isset($payload['ownerId'], $payload['rawText'], $payload['type']) || !is_string($payload['rawText']) || !is_string($payload['type'])) {
            throw new ValidationException('ownerId, rawText and type are required.');
        }

        $ownerId = filter_var($payload['ownerId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($ownerId === false) {
            throw new ValidationException('ownerId must be a positive integer.');
        }

        if (trim($payload['rawText']) === '' || trim($payload['type']) === '') {
            throw new ValidationException('rawText and type cannot be empty.');
        }
    }

    private function normalizeLimit(int $limit): int
    {
        if ($limit < 1) {
            return 1;
        }

        if ($limit > 100) {
            return 100;
        }

        return $limit;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRequest(RequestEntity $requestEntity, bool $includeRawText = true): array
    {
        $data = [
            'id' => $requestEntity->getId(),
            'ownerId' => $requestEntity->getOwner()->getId(),
            'type' => $requestEntity->getType(),
            'city' => $requestEntity->getCity(),
            'country' => $requestEntity->getCountry(),
            'status' => $requestEntity->getStatus(),
            'createdAt' => $requestEntity->getCreatedAt()->format(DATE_ATOM),
        ];

        if ($includeRawText) {
            $data['rawText'] = $requestEntity->getRawText();
        }

        return $data;
    }
}
