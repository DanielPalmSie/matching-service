<?php

namespace App\Logging;

use Elastica\Client;
use Elastica\Document;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class ElasticsearchMonologHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly Client $client,
        private readonly string $indexName,
        Level $level = Level::Info,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $this->doWrite($record);
        } catch (\Throwable $e) {
            // В stderr, без Monolog (иначе цикл)
            error_log('ELASTIC_HANDLER_FAIL: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Throwable
     */
    private function doWrite(LogRecord $record): void
    {
        /** @var array<string, mixed> $data */
        $data = $record->toArray();

        // 1) datetime -> строка (на всякий случай)
        if (isset($data['datetime']) && $data['datetime'] instanceof \DateTimeInterface) {
            $data['datetime'] = $data['datetime']->format(DATE_ATOM);
        }

        // 2) context/extra могут содержать объекты -> приводим к JSON-safe
        $data['context'] = $this->normalizeToJsonSafe($data['context'] ?? []);
        $data['extra']   = $this->normalizeToJsonSafe($data['extra'] ?? []);

        $index = $this->client->getIndex($this->indexName);
        $index->addDocument(new Document(null, $data));
    }

    private function normalizeToJsonSafe(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return '[unserializable]';
        }
    }
}
