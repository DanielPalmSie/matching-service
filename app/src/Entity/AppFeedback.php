<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AppFeedbackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppFeedbackRepository::class)]
#[ORM\Table(name: 'app_feedback')]
#[ORM\Index(name: 'idx_app_feedback_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_app_feedback_main_issue', columns: ['main_issue'])]
#[ORM\Index(name: 'idx_app_feedback_rating', columns: ['rating'])]
class AppFeedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id', type: 'bigint')]
    private int $userId;

    #[ORM\Column(type: 'smallint')]
    private int $rating;

    #[ORM\Column(name: 'main_issue', length: 50, nullable: true)]
    private ?string $mainIssue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(int $userId, int $rating, ?string $mainIssue = null, ?string $comment = null, ?\DateTimeImmutable $createdAt = null)
    {
        $this->userId = $userId;
        $this->rating = $rating;
        $this->mainIssue = $mainIssue;
        $this->comment = $comment;
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

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): void
    {
        $this->rating = $rating;
    }

    public function getMainIssue(): ?string
    {
        return $this->mainIssue;
    }

    public function setMainIssue(?string $mainIssue): void
    {
        $this->mainIssue = $mainIssue;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
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
