<?php

declare(strict_types=1);

namespace App\Service;

use OpenAI\Client;
use OpenAI\Factory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OpenAIService
{
    private Client $client;

    public function __construct(
        #[Autowire('%env(OPENAI_API_KEY)%')] private readonly string $apiKey,
        private readonly LoggerInterface $logger
    ) {
        $this->client = (new Factory())
            ->withApiKey($this->apiKey)
            ->make();
    }

    /**
     * @param array<int, string> $comments
     *
     * @return array<int, array<string, mixed>>
     */
    public function clusterFeedback(array $comments): array
    {
        if ($comments === []) {
            return [];
        }

        $prompt = $this->buildPrompt($comments);

        $response = $this->client->chat()->create([
            'model' => 'gpt-4.1',
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are an analytics assistant that clusters user feedback.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '';
        $this->logger->info('Received clustering response from OpenAI.', ['length' => strlen((string) $content)]);

        $decoded = json_decode((string) $content, true);
        if (!is_array($decoded)) {
            $this->logger->error('Failed to decode OpenAI clustering response.', ['content' => $content]);

            return [];
        }

        if (isset($decoded['clusters']) && is_array($decoded['clusters'])) {
            return array_values($decoded['clusters']);
        }

        return array_values($decoded);
    }

    /**
     * @param array<int, string> $comments
     */
    private function buildPrompt(array $comments): string
    {
        $joined = "- " . implode("\n- ", array_map(fn (string $comment) => trim($comment), $comments));

        return <<<PROMPT
Cluster the following user feedback comments from the last week into themes. Return a JSON object with a "clusters" key whose value is an array of clusters. Each cluster must contain:
- label: short title for the cluster
- description: 1-2 sentence summary of the theme
- count: number of comments in this cluster
- percentage: percentage of total comments in this cluster
- examples: up to 3 representative comments

Comments:
$joined
PROMPT;
    }
}
