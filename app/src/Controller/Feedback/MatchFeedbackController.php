<?php

declare(strict_types=1);

namespace App\Controller\Feedback;

use App\DTO\Feedback\MatchFeedbackRequest;
use App\Service\FeedbackService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MatchFeedbackController
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/feedback/match', name: 'api_feedback_match', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $dto = new MatchFeedbackRequest(
            userId: $payload['userId'] ?? null,
            matchId: $payload['matchId'] ?? null,
            targetRequestId: $payload['targetRequestId'] ?? null,
            relevanceScore: $payload['relevanceScore'] ?? null,
            reasonCode: $payload['reasonCode'] ?? null,
            comment: $payload['comment'] ?? null,
            mainIssue: $payload['mainIssue'] ?? null,
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->feedbackService->submitMatchFeedback($dto);

        return new JsonResponse(['status' => 'ok'], Response::HTTP_CREATED);
    }
}
