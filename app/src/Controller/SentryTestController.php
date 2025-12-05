<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SentryTestController extends AbstractController
{
    #[Route('/_sentry-test', name: 'sentry_test')]
    public function testLog(): Response
    {
        throw new \RuntimeException('PROD controller Sentry test');
    }
}
