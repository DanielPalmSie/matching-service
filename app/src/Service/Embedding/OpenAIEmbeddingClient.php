<?php

declare(strict_types=1);

namespace App\Service\Embedding;

use RuntimeException;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAIEmbeddingClient implements EmbeddingClientInterface
{
    use HttpClientTrait;

    private string $apiKey;

    private string $model;

    public function __construct(HttpClientInterface $httpClient, string $apiKey, string $model)
    {
        $this->client = $httpClient;
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function embed(string $text): array
    {
        try {
            $response = $this->client->request('POST', 'https://api.openai.com/v1/embeddings', [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->apiKey),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'input' => $text,
                ],
            ]);
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException('Failed to contact embedding provider.', 0, $exception);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new RuntimeException(sprintf('Embedding provider returned HTTP %d.', $statusCode));
        }

        $data = $response->toArray(false);
        if (!isset($data['data'][0]['embedding']) || !is_array($data['data'][0]['embedding'])) {
            throw new RuntimeException('Embedding provider returned an invalid response payload.');
        }

        $embedding = array_map(static fn ($value) => (float) $value, $data['data'][0]['embedding']);

        return $embedding;
    }
}
