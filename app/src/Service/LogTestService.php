<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class LogTestService
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function logHttpTest(): void
    {
        $this->logger->info('ELK HTTP TEST', [
            'range_start' => (new DateTimeImmutable('-1 day'))->format(DateTimeImmutable::ATOM),
            'range_end' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'source' => 'http',
        ]);
    }
}
