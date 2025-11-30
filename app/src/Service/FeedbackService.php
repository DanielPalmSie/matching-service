<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Feedback\AppFeedbackRequest;
use App\DTO\Feedback\MatchFeedbackRequest;
use App\Entity\AppFeedback;
use App\Entity\MatchFeedback;
use Doctrine\ORM\EntityManagerInterface;

class FeedbackService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function submitMatchFeedback(MatchFeedbackRequest $dto): MatchFeedback
    {
        $feedback = new MatchFeedback(
            userId: (int) $dto->userId,
            comment: $dto->comment,
            reasonCode: $dto->reasonCode,
            relevanceScore: (int) $dto->relevanceScore,
            createdAt: null,
            matchId: $dto->matchId,
            targetRequestId: $dto->targetRequestId,
            mainIssue: $dto->mainIssue,
        );

        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        return $feedback;
    }

    public function submitAppFeedback(AppFeedbackRequest $dto): AppFeedback
    {
        $feedback = new AppFeedback(
            userId: (int) $dto->userId,
            rating: (int) $dto->rating,
            mainIssue: $dto->mainIssue,
            comment: $dto->comment,
        );

        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        return $feedback;
    }
}
