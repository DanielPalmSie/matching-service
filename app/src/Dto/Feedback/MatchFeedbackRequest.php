<?php

declare(strict_types=1);

namespace App\Dto\Feedback;

use Symfony\Component\Validator\Constraints as Assert;

class MatchFeedbackRequest
{
    public const ALLOWED_RELEVANCE_SCORES = [-1, 0, 1, 2];

    public const ALLOWED_REASON_CODES = [
        'not_relevant',
        'too_far',
        'old_request',
        'spam',
        'language_mismatch',
    ];

    public const ALLOWED_MAIN_ISSUES = [
        'irrelevant_matches',
        'few_users',
        'missing_features',
        'bugs',
        'performance',
        'hard_to_use',
        'other',
    ];

    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        public readonly ?int $userId = null,
        #[Assert\Type('integer')]
        public readonly ?int $matchId = null,
        #[Assert\Type('integer')]
        public readonly ?int $targetRequestId = null,
        #[Assert\NotNull]
        #[Assert\Type('integer')]
        #[Assert\Choice(choices: self::ALLOWED_RELEVANCE_SCORES)]
        public readonly ?int $relevanceScore = null,
        #[Assert\Choice(choices: self::ALLOWED_REASON_CODES)]
        public readonly ?string $reasonCode = null,
        #[Assert\Type('string')]
        public readonly ?string $comment = null,
        #[Assert\Choice(choices: self::ALLOWED_MAIN_ISSUES)]
        public readonly ?string $mainIssue = null,
    ) {
    }
}
