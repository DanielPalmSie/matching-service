<?php

declare(strict_types=1);

namespace App\Service\Feedback;

use App\Dto\Feedback\AppFeedbackRequest;
use App\Dto\Feedback\MatchFeedbackRequest;
use App\Service\Exception\ValidationException;
use App\Service\FeedbackService;
use App\Service\Http\JsonPayloadDecoder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FeedbackSubmissionService
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
        private readonly ValidatorInterface $validator,
        private readonly JsonPayloadDecoder $payloadDecoder,
    ) {
    }

    public function submitMatch(Request $request): void
    {
        $payload = $this->payloadDecoder->decode($request, 'Invalid JSON body.');

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
            throw new ValidationException((string) $errors);
        }

        $this->feedbackService->submitMatchFeedback($dto);
    }

    public function submitApp(Request $request): void
    {
        $payload = $this->payloadDecoder->decode($request, 'Invalid JSON body.');

        $dto = new AppFeedbackRequest(
            userId: $payload['userId'] ?? null,
            rating: $payload['rating'] ?? null,
            mainIssue: $payload['mainIssue'] ?? null,
            comment: $payload['comment'] ?? null,
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new ValidationException((string) $errors);
        }

        $this->feedbackService->submitAppFeedback($dto);
    }
}
