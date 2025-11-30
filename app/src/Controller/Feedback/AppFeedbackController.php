<?php

declare(strict_types=1);

namespace App\Controller\Feedback;

use App\Dto\Feedback\AppFeedbackRequest;
use App\Service\FeedbackService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AppFeedbackController
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/feedback/app', name: 'api_feedback_app', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $dto = new AppFeedbackRequest(
            userId: $payload['userId'] ?? null,
            rating: $payload['rating'] ?? null,
            mainIssue: $payload['mainIssue'] ?? null,
            comment: $payload['comment'] ?? null,
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->feedbackService->submitAppFeedback($dto);

        return new JsonResponse(['status' => 'ok'], Response::HTTP_CREATED);
    }
}
