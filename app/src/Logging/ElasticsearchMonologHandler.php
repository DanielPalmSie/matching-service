<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeInterface;
use Elastica\Client;
use Elastica\Document;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

final class ElasticsearchMonologHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly Client $client,
        private readonly string $indexName,
        int|string|Level $level = Level::Info,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * @param LogRecord|array<string, mixed> $record
     */
    protected function write(mixed $record): void
    {
        try {
            $payload = $this->buildPayload($record);
            $document = new Document(null, $payload);
            $this->client->getIndex($this->indexName)->addDocument($document);
        } catch (Throwable) {
            // Swallow all exceptions to avoid breaking the application.
        }
    }

    /**
     * @param LogRecord|array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function buildPayload(mixed $record): array
    {
        if ($record instanceof LogRecord) {
            return [
                'message' => $record->message,
                'context' => $this->normalizeContext($record->context),
                'extra' => $this->normalizeContext($record->extra),
                'level' => $record->level->value,
                'level_name' => $record->level->name,
                'channel' => $record->channel,
                'datetime' => $this->formatDateTime($record->datetime),
            ];
        }

        $levelName = $record['level_name'] ?? null;
        $levelValue = $record['level'] ?? null;

        return [
            'message' => $record['message'] ?? '',
            'context' => $this->normalizeContext($record['context'] ?? []),
            'extra' => $this->normalizeContext($record['extra'] ?? []),
            'level' => is_numeric($levelValue) ? (int) $levelValue : $levelValue,
            'level_name' => is_string($levelName) ? $levelName : null,
            'channel' => $record['channel'] ?? null,
            'datetime' => $this->formatDateTime($record['datetime'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function normalizeContext(array $data): string
    {
        if ($data === []) {
            return '{}';
        }

        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        return $json === false ? '[unserializable]' : $json;
    }

    private function formatDateTime(mixed $dateTime): ?string
    {
        if ($dateTime instanceof DateTimeInterface) {
            return $dateTime->format(DateTimeInterface::ATOM);
        }

        if (is_string($dateTime)) {
            return $dateTime;
        }

        return null;
    }
}
