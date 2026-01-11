<?php

namespace App\Controller;

use App\Service\LogTestService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LogTestController
{
    public function __construct(private readonly LogTestService $logTestService)
    {
    }

    #[Route('/api/log-test', name: 'api_log_test', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $this->logTestService->logHttpTest();

        return new JsonResponse(['ok' => true]);
    }
}
