<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Request as RequestEntity;
use App\Entity\User;
use App\Repository\RequestRepository;
use App\Service\Embedding\EmbeddingClientInterface;
use App\Service\Exception\NotFoundException;
use App\Service\Exception\ValidationException;
use App\Service\Matching\MatchingEngineInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RequestService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestRepository $requestRepository,
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly MatchingEngineInterface $matchingEngine,
        #[Autowire('%env(OPENAI_EMBEDDING_MODEL)%')]
        private readonly string $embeddingModel,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createRequest(array $payload, User $owner): array
    {
        $this->assertCreatePayload($payload, $owner);

        $embedding = null;
        $embeddingStatus = 'ready';
        $embeddingError = null;
        $embeddingUpdatedAt = new DateTimeImmutable();
        try {
            $embeddingRaw = $this->embeddingClient->embed($payload['rawText']);

            /** @var list<float> $embedding */
            $embedding = array_map('floatval', $embeddingRaw);
        } catch (\Throwable $exception) {
            $embeddingStatus = 'pending';
            $embeddingError = $exception->getMessage();
            $this->logger->error('Failed to embed request content.', [
                'ownerId' => $owner->getId(),
                'exception' => $exception,
            ]);
        }

        $requestEntity = new RequestEntity();
        $requestEntity->setOwner($owner);
        $requestEntity->setRawText($payload['rawText']);
        $requestEntity->setCity($payload['city'] ?? null);
        $requestEntity->setCountry($payload['country'] ?? null);
        $requestEntity->setStatus('active');
        $requestEntity->setCreatedAt(new DateTimeImmutable());
        $requestEntity->setEmbedding($embedding);
        $requestEntity->setEmbeddingModel($this->embeddingModel);
        $requestEntity->setEmbeddingStatus($embeddingStatus);
        $requestEntity->setEmbeddingUpdatedAt($embeddingUpdatedAt);
        $requestEntity->setEmbeddingError($embeddingError);

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

        if ($requestEntity->getEmbedding() === null || $requestEntity->getEmbeddingStatus() !== 'ready') {
            $this->logger->warning('request.matches.embedding_missing', [
                'request_id' => $requestEntity->getId(),
                'owner_id' => $requestEntity->getOwner()->getId(),
                'embedding_status' => $requestEntity->getEmbeddingStatus(),
            ]);

            return [];
        }

        $normalizedLimit = $this->normalizeLimit($limit);
        $matches = $this->matchingEngine->findMatches($requestEntity, $normalizedLimit);
        $matchedOwners = array_map(
            static fn (array $match): array => [
                'request_id' => $match['request']->getId(),
                'owner_id' => $match['request']->getOwner()->getId(),
            ],
            $matches,
        );
        $this->logger->info('request.matches', [
            'current_user_id' => $requestEntity->getOwner()->getId(),
            'source_request_id' => $requestEntity->getId(),
            'matches' => $matchedOwners,
        ]);

        return array_map(
            fn (array $match) => $this->mapRequest($match['request'], false) + [
                'similarity' => $match['similarity'],
                'rawTextShort' => $this->buildRawTextShort($match['request']->getRawText()),
            ],
            $matches,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRequestsForOwner(User $owner, int $offset = 0, ?int $limit = null): array
    {
        $normalizedOffset = max(0, $offset);
        $normalizedLimit = $limit !== null ? $this->normalizeLimit($limit) : null;

        $requests = $this->requestRepository->findActiveByOwner($owner, $normalizedOffset, $normalizedLimit);

        return array_map(fn (RequestEntity $requestEntity) => $this->mapRequest($requestEntity), $requests);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertCreatePayload(array $payload, User $owner): void
    {
        if (!isset($payload['rawText']) || !is_string($payload['rawText'])) {
            throw new ValidationException('rawText is required.');
        }

        if (trim($payload['rawText']) === '') {
            throw new ValidationException('rawText cannot be empty.');
        }

        if (isset($payload['ownerId'])) {
            $ownerId = filter_var($payload['ownerId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($ownerId === false) {
                throw new ValidationException('ownerId must be a positive integer that matches the authenticated user.');
            }

            if ($ownerId !== $owner->getId()) {
                throw new ValidationException('ownerId does not match the authenticated user. This field is deprecated; omit it to rely on the logged-in user.');
            }
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

    private function buildRawTextShort(string $rawText, int $limit = 120): string
    {
        $trimmed = trim($rawText);
        if (mb_strlen($trimmed) <= $limit) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, $limit) . '…';
    }
}
