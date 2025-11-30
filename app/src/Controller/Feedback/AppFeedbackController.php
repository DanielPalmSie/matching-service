<?php

declare(strict_types=1);

namespace App\Controller\Feedback;

use App\Dto\Feedback\AppFeedbackRequest;
use App\Service\FeedbackService;
use OpenApi\Attributes as OA;
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
    #[OA\Post(
        path: '/api/feedback/app',
        summary: 'Submit application feedback',
        description: 'Collects overall application feedback such as rating, main issue and optional comment.',
        tags: ['Feedback'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['userId', 'rating'],
                properties: [
                    new OA\Property(property: 'userId', type: 'integer', example: 12, description: 'Identifier of the user providing feedback.'),
                    new OA\Property(property: 'rating', type: 'integer', example: 5, description: 'Overall rating from 1 to 5.'),
                    new OA\Property(property: 'mainIssue', type: 'string', nullable: true, example: 'missing_features', description: 'Optional predefined issue category.'),
                    new OA\Property(property: 'comment', type: 'string', nullable: true, example: 'Great app, but I would like more filters.', description: 'Optional free-text comment.'),
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
