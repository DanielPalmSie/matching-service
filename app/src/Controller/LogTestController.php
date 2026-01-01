<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LogTestController
{
    #[Route('/api/log-test', name: 'api_log_test', methods: ['GET'])]
    public function __invoke(LoggerInterface $logger): JsonResponse
    {
        $logger->info('ELK HTTP TEST', [
            'range_start' => (new \DateTimeImmutable('-1 day'))->format(\DateTimeImmutable::ATOM),
            'range_end' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            'source' => 'http',
        ]);

        return new JsonResponse(['ok' => true]);
    }
}
