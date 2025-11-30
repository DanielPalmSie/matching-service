<?php

declare(strict_types=1);

namespace App\Controller\Feedback;

use App\Dto\Feedback\MatchFeedbackRequest;
use App\Service\FeedbackService;
use OpenApi\Attributes as OA;
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
    #[OA\Post(
        path: '/api/feedback/match',
        summary: 'Submit match feedback',
        description: 'Collects feedback about a specific match including relevance score and optional reasons.',
        tags: ['Feedback'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['userId', 'relevanceScore'],
                properties: [
                    new OA\Property(property: 'userId', type: 'integer', example: 12, description: 'Identifier of the user providing feedback.'),
                    new OA\Property(property: 'matchId', type: 'integer', nullable: true, example: 45, description: 'Identifier of the match entity if available.'),
                    new OA\Property(property: 'targetRequestId', type: 'integer', nullable: true, example: 84, description: 'Identifier of the target request referenced by the match.'),
                    new OA\Property(property: 'relevanceScore', type: 'integer', example: 1, description: 'Relevance score in the range -1..2.'),
                    new OA\Property(property: 'reasonCode', type: 'string', nullable: true, example: 'not_relevant', description: 'Optional reason code for negative feedback.'),
                    new OA\Property(property: 'comment', type: 'string', nullable: true, example: 'Profile does not match the request.', description: 'Optional free-text comment.'),
                    new OA\Property(property: 'mainIssue', type: 'string', nullable: true, example: 'irrelevant_matches', description: 'Optional predefined issue category.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'Feedback was accepted and stored.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'string', example: 'ok')])
            ),
            new OA\Response(
                response: Response::HTTP_BAD_REQUEST,
                description: 'Invalid JSON body or validation failed.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string')])
            ),
        ],
    )]
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
