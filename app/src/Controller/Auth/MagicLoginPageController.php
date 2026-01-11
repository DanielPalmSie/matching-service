<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\MagicLoginPageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MagicLoginPageController extends AbstractController
{
    public function __construct(
        private readonly MagicLoginPageService $magicLoginPageService,
    ) {
    }

    #[Route('/auth/magic-login/{token}', name: 'magic_login_page', methods: ['GET'])]
    public function __invoke(string $token): Response
    {
        if (!$this->magicLoginPageService->handle($token)) {
            return $this->render('auth/magic_login_error.html.twig');
        }

        return $this->render('auth/magic_login_success.html.twig');
    }
}
