<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SentryRawController extends AbstractController
{
    #[Route('/_sentry-raw', name: 'sentry_raw')]
    public function raw(): Response
    {
        \Sentry\init([
            'dsn' => $_ENV['SENTRY_DSN'],
            'environment' => 'dev',
        ]);

        \Sentry\captureMessage('🔥 RAW test from PHP SDK');

        return new Response('sent');
    }
}
