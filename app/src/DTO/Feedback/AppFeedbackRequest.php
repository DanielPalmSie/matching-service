<?php

declare(strict_types=1);

namespace App\DTO\Feedback;

use Symfony\Component\Validator\Constraints as Assert;

class AppFeedbackRequest
{
    public const ALLOWED_RATINGS = [1, 2, 3, 4, 5];

    public const ALLOWED_MAIN_ISSUES = MatchFeedbackRequest::ALLOWED_MAIN_ISSUES;

    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        public readonly ?int $userId = null,
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        #[Assert\Choice(choices: self::ALLOWED_RATINGS)]
        public readonly ?int $rating = null,
        #[Assert\Choice(choices: self::ALLOWED_MAIN_ISSUES)]
        public readonly ?string $mainIssue = null,
        #[Assert\Type('string')]
        public readonly ?string $comment = null,
    ) {
    }
}
