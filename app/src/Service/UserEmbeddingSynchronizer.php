<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserEmbeddingRepository;
use App\Service\Embedding\EmbeddingClientInterface;

final class UserEmbeddingSynchronizer
{
    public function __construct(
        private readonly EmbeddingClientInterface $embeddingClient,
        private readonly UserEmbeddingRepository $userEmbeddingRepository,
    ) {
    }

    public function sync(User $user, ?string $sourceText = null): bool
    {
        if ($user->getId() === null) {
            return false;
        }

        $profileText = $sourceText ?? $this->buildProfileText($user);
        $normalizedText = trim($profileText);

        if ($normalizedText === '' && $sourceText !== null) {
            $normalizedText = trim($this->buildProfileText($user));
        }

        if ($normalizedText === '') {
            return false;
        }

        $embedding = $this->embeddingClient->embed($normalizedText);

        $this->userEmbeddingRepository->upsert($user->getId(), $embedding);

        return true;
    }

    private function buildProfileText(User $user): string
    {
        return implode(' ', array_filter([
            $user->getDisplayName(),
            $user->getCity(),
            $user->getCountry(),
            $user->getTimezone(),
            $user->getExternalId(),
        ], static fn (?string $value) => $value !== null && trim($value) !== ''));
    }
}
