<?php

namespace App\Logging;

use Elastica\Client;
use Elastica\Document;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class ElasticsearchMonologHandler extends AbstractProcessingHandler
{
    private Client $client;

    public function __construct(
        private readonly string $elasticsearchUrl,
        private readonly string $indexName,
        Level $level = Level::Info,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);

        $host = preg_replace('#^https?://#', '', rtrim($this->elasticsearchUrl, '/'));

        $this->client = new Client([
            'hosts' => [$host],
        ]);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $data = [
                'message'    => $record->message,
                'context'    => $this->toJsonString($record->context),
                'level'      => $record->level->value,
                'level_name' => $record->level->getName(),
                'channel'    => $record->channel,
                'datetime'   => $record->datetime->format(DATE_ATOM),
                'extra'      => $this->toJsonString($record->extra),
            ];

            $index = $this->client->getIndex($this->indexName);
            $index->addDocument(new Document(null, $data));
        } catch (\Throwable $e) {
            error_log('ELASTIC_HANDLER_FAIL: ' . $e->getMessage());
        }
    }

    private function toJsonString(mixed $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '[unserializable]';
        }
    }
}
