<?php

namespace App\Entity;

use App\Repository\RequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestRepository::class)]
class Request
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'requests')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $rawText;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $lat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $lng = null;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var list<float>|null
     */
    #[ORM\Column(type: 'vector', nullable: true, columnDefinition: 'vector(3072)')]
    private ?array $embedding = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $embeddingModel = null;

    #[ORM\Column(length: 16)]
    private string $embeddingStatus = 'ready';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $embeddingUpdatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $embeddingError = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getRawText(): string
    {
        return $this->rawText;
    }

    public function setRawText(string $rawText): static
    {
        $this->rawText = $rawText;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): static
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLng(): ?float
    {
        return $this->lng;
    }

    public function setLng(?float $lng): static
    {
        $this->lng = $lng;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return list<float>|null
     */
    public function getEmbedding(): ?array
    {
        return $this->embedding;
    }

    /**
     * @param list<float>|null $embedding
     */
    public function setEmbedding(?array $embedding): static
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function getEmbeddingModel(): ?string
    {
        return $this->embeddingModel;
    }

    public function setEmbeddingModel(?string $embeddingModel): static
    {
        $this->embeddingModel = $embeddingModel;

        return $this;
    }

    public function getEmbeddingStatus(): string
    {
        return $this->embeddingStatus;
    }

    public function setEmbeddingStatus(string $embeddingStatus): static
    {
        $this->embeddingStatus = $embeddingStatus;

        return $this;
    }

    public function getEmbeddingUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->embeddingUpdatedAt;
    }

    public function setEmbeddingUpdatedAt(?\DateTimeImmutable $embeddingUpdatedAt): static
    {
        $this->embeddingUpdatedAt = $embeddingUpdatedAt;

        return $this;
    }

    public function getEmbeddingError(): ?string
    {
        return $this->embeddingError;
    }

    public function setEmbeddingError(?string $embeddingError): static
    {
        $this->embeddingError = $embeddingError;

        return $this;
    }
}
