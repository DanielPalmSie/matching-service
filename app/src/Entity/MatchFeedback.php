<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MatchFeedbackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchFeedbackRepository::class)]
#[ORM\Table(name: 'match_feedback')]
#[ORM\Index(name: 'idx_match_feedback_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_match_feedback_reason_code', columns: ['reason_code'])]
#[ORM\Index(name: 'idx_match_feedback_main_issue', columns: ['main_issue'])]
class MatchFeedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(name: 'match_id', nullable: true)]
    private ?int $matchId = null;

    #[ORM\Column(name: 'target_request_id', nullable: true)]
    private ?int $targetRequestId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'reason_code', length: 32, nullable: true)]
    private ?string $reasonCode = null;

    #[ORM\Column(name: 'relevance_score', type: 'smallint')]
    private int $relevanceScore;

    #[ORM\Column(name: 'main_issue', length: 50, nullable: true)]
    private ?string $mainIssue = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $userId,
        ?string $comment,
        ?string $reasonCode,
        int $relevanceScore,
        ?\DateTimeImmutable $createdAt = null,
        ?int $matchId = null,
        ?int $targetRequestId = null,
        ?string $mainIssue = null
    ) {
        $this->userId = $userId;
        $this->comment = $comment;
        $this->reasonCode = $reasonCode;
        $this->relevanceScore = $relevanceScore;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->matchId = $matchId;
        $this->targetRequestId = $targetRequestId;
        $this->mainIssue = $mainIssue;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getMatchId(): ?int
    {
        return $this->matchId;
    }

    public function setMatchId(?int $matchId): void
    {
        $this->matchId = $matchId;
    }

    public function getTargetRequestId(): ?int
    {
        return $this->targetRequestId;
    }

    public function setTargetRequestId(?int $targetRequestId): void
    {
        $this->targetRequestId = $targetRequestId;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function setReasonCode(?string $reasonCode): void
    {
        $this->reasonCode = $reasonCode;
    }

    public function getRelevanceScore(): int
    {
        return $this->relevanceScore;
    }

    public function setRelevanceScore(int $relevanceScore): void
    {
        $this->relevanceScore = $relevanceScore;
    }

    public function getMainIssue(): ?string
    {
        return $this->mainIssue;
    }

    public function setMainIssue(?string $mainIssue): void
    {
        $this->mainIssue = $mainIssue;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
