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
        private readonly string $indexName,
        private readonly string $elasticsearchUrl,
        Level $level = Level::Info,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
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

            $client = new Client(['url' => $this->elasticsearchUrl]);
            $index  = $client->getIndex($this->indexName);
            $index->addDocument(new Document(null, $data));
        } catch (\Throwable $e) {
            error_log('ELASTIC_HANDLER_FAIL: ' . $e->getMessage());
        }
    }

    private function toJsonString(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            return '[unserializable]';
        }
    }
}
