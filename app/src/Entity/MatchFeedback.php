<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MatchFeedbackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchFeedbackRepository::class)]
#[ORM\Table(name: 'match_feedback')]
#[ORM\Index(name: 'idx_match_feedback_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_match_feedback_reason_code', columns: ['reason_code'])]
class MatchFeedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'reason_code', length: 32, nullable: true)]
    private ?string $reasonCode = null;

    #[ORM\Column(name: 'relevance_score', type: 'smallint')]
    private int $relevanceScore;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $userId,
        ?string $comment,
        ?string $reasonCode,
        int $relevanceScore,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->userId = $userId;
        $this->comment = $comment;
        $this->reasonCode = $reasonCode;
        $this->relevanceScore = $relevanceScore;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
