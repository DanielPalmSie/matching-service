<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Service\Auth\MagicLinkRequestService;
use App\Service\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

class MagicLinkController extends AbstractController
{
    public function __construct(
        private readonly MagicLinkRequestService $magicLinkRequestService,
    ) {
    }

    #[Route('/api/auth/magic-link/request', name: 'auth_magic_link_request', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        try {
            $this->magicLinkRequestService->requestLink($request);
        } catch (ValidationException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (TransportExceptionInterface $exception) {
            return new JsonResponse(
                ['error' => 'Failed to send magic login link'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
