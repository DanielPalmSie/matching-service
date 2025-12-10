<?php

declare(strict_types=1);

namespace App\Service\Embedding;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAIEmbeddingClient implements EmbeddingClientInterface
{
    private const EXPECTED_DIMENSIONS = 3072;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openAiApiKey,
        private readonly string $openAiEmbeddingModel,
    ) {
    }

    /**
     * @return float[]
     */
    public function embed(string $text): array
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model' => $this->openAiEmbeddingModel,
                'input' => $text,
            ],
        ]);

        $data = $response->toArray(false);

        if (!isset($data['data'][0]['embedding']) || !is_array($data['data'][0]['embedding'])) {
            throw new \RuntimeException('Unexpected OpenAI embeddings response structure');
        }

        /** @var array<int, float> $embedding */
        $embedding = array_map('floatval', $data['data'][0]['embedding']);

        $dimensionCount = count($embedding);

        if ($dimensionCount !== self::EXPECTED_DIMENSIONS) {
            throw new \RuntimeException(sprintf(
                'Embedding dimension mismatch: expected %d values for model %s but received %d.',
                self::EXPECTED_DIMENSIONS,
                $this->openAiEmbeddingModel,
                $dimensionCount,
            ));
        }

        return $embedding;
    }
}
