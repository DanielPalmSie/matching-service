<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class SentryTestController extends AbstractController
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[Route('/_sentry-test', name: 'sentry_test')]
    public function testLog()
    {
        // Проверка, что monolog шлёт в Sentry
        $this->logger->error('My custom logged error.', ['some' => 'Context Data']);

        // Проверка, что необработанные исключения тоже летят в Sentry
        throw new \RuntimeException('Example exception.');
    }
}
