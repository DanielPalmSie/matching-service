<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Request as RequestEntity;
use App\Repository\RequestRepository;
use App\Repository\UserRepository;
use App\Service\Embedding\EmbeddingClientInterface;
use App\Service\Matching\MatchingEngineInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RequestController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestRepository $requestRepository,
        private readonly UserRepository $userRepository,
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly MatchingEngineInterface $matchingEngine
    ) {
    }

    #[Route('/api/requests', name: 'api_requests_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['ownerId'], $payload['rawText'], $payload['type']) || !is_string($payload['rawText']) || !is_string($payload['type'])) {
            return new JsonResponse(['error' => 'ownerId, rawText and type are required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $ownerId = filter_var($payload['ownerId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($ownerId === false) {
            return new JsonResponse(['error' => 'ownerId must be a positive integer.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (trim($payload['rawText']) === '' || trim($payload['type']) === '') {
            return new JsonResponse(['error' => 'rawText and type cannot be empty.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $owner = $this->userRepository->find($ownerId);
        if ($owner === null) {
            return new JsonResponse(['error' => 'Owner not found.'], JsonResponse::HTTP_NOT_FOUND);
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

        return new JsonResponse([
            'id' => $requestEntity->getId(),
            'ownerId' => $requestEntity->getOwner()->getId(),
            'rawText' => $requestEntity->getRawText(),
            'type' => $requestEntity->getType(),
            'city' => $requestEntity->getCity(),
            'country' => $requestEntity->getCountry(),
            'status' => $requestEntity->getStatus(),
            'createdAt' => $requestEntity->getCreatedAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/api/requests/{id}', name: 'api_requests_get', methods: ['GET'])]
    public function getRequest(int $id): JsonResponse
    {
        $requestEntity = $this->requestRepository->find($id);
        if ($requestEntity === null) {
            return new JsonResponse(['error' => 'Request not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $requestEntity->getId(),
            'ownerId' => $requestEntity->getOwner()->getId(),
            'rawText' => $requestEntity->getRawText(),
            'type' => $requestEntity->getType(),
            'city' => $requestEntity->getCity(),
            'country' => $requestEntity->getCountry(),
            'status' => $requestEntity->getStatus(),
            'createdAt' => $requestEntity->getCreatedAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/api/requests/{id}/matches', name: 'api_requests_matches', methods: ['GET'])]
    public function getMatches(int $id, Request $request): JsonResponse
    {
        $requestEntity = $this->requestRepository->find($id);
        if ($requestEntity === null) {
            return new JsonResponse(['error' => 'Request not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $limit = (int) ($request->query->get('limit', 20));
        if ($limit < 1) {
            $limit = 1;
        } elseif ($limit > 100) {
            $limit = 100;
        }
        $matches = $this->matchingEngine->findMatches($requestEntity, $limit);

        $data = array_map(static function (RequestEntity $match) {
            return [
                'id' => $match->getId(),
                'ownerId' => $match->getOwner()->getId(),
                'type' => $match->getType(),
                'city' => $match->getCity(),
                'country' => $match->getCountry(),
                'status' => $match->getStatus(),
                'createdAt' => $match->getCreatedAt()->format(DATE_ATOM),
            ];
        }, $matches);

        return new JsonResponse($data);
    }
}
