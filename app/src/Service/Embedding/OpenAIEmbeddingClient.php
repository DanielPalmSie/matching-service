<?php

declare(strict_types=1);

namespace App\Service\Embedding;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAIEmbeddingClient implements EmbeddingClientInterface
{
    private const EXPECTED_DIMENSIONS = 3072;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openAiApiKey,
        private readonly string $openAiEmbeddingModel,
        private readonly LoggerInterface $logger,
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

        $statusCode = $response->getStatusCode();
        $rawBody = $response->getContent(false);
        $headers = $response->getHeaders(false);
        $requestId = $headers['x-request-id'][0] ?? null;

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->error('Failed to decode OpenAI embeddings response as JSON.', [
                'status' => $statusCode,
                'request_id' => $requestId,
                'raw_body' => mb_substr($rawBody, 0, 1000),
            ]);

            throw new \RuntimeException('Failed to decode OpenAI embeddings response as JSON.', 0, $exception);
        }

        if (isset($data['error'])) {
            $error = is_array($data['error']) ? $data['error'] : [];

            $this->logger->error('OpenAI embeddings request failed.', [
                'status' => $statusCode,
                'error_type' => $error['type'] ?? null,
                'error_code' => $error['code'] ?? null,
                'error_message' => $error['message'] ?? null,
                'request_id' => $requestId,
                'response' => $data,
            ]);

            throw new \RuntimeException(sprintf(
                'OpenAI embeddings error: %s (type: %s, code: %s, request_id: %s)',
                $error['message'] ?? 'Unknown error',
                $error['type'] ?? 'unknown',
                $error['code'] ?? 'unknown',
                $requestId ?? 'unknown',
            ));
        }

        if (!isset($data['data']) || !is_array($data['data']) || $data['data'] === []) {
            $this->logger->error('Unexpected OpenAI embeddings response structure.', [
                'status' => $statusCode,
                'request_id' => $requestId,
                'response' => $data,
            ]);

            throw new \RuntimeException('Unexpected OpenAI embeddings response structure');
        }

        if (!isset($data['data'][0]['embedding']) || !is_array($data['data'][0]['embedding'])) {
            $this->logger->error('Unexpected OpenAI embeddings response structure.', [
                'status' => $statusCode,
                'request_id' => $requestId,
                'response' => $data,
            ]);

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
