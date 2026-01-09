<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\MagicLoginToken;
use App\Repository\MagicLoginTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

class MagicLoginPageController extends AbstractController
{
    public function __construct(
        private readonly MagicLoginTokenRepository $magicLoginTokenRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly HubInterface $hub,
        #[Autowire(service: 'monolog.logger.magic_login')]
        private readonly LoggerInterface $magicLoginLogger,
    ) {
    }

    #[Route('/auth/magic-login/{token}', name: 'magic_login_page', methods: ['GET'])]
    public function __invoke(string $token): Response
    {
        $magicToken = $this->magicLoginTokenRepository->findOneBy(['token' => $token]);

        if (!$magicToken instanceof MagicLoginToken || !$magicToken->isValid()) {
            return $this->render('auth/magic_login_error.html.twig');
        }

        $magicToken->setUsedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        $jwt = $this->jwtTokenManager->create($magicToken->getUser());

        $telegramChatId = $magicToken->getTelegramChatId();

        if ($telegramChatId !== null) {
            $topic = sprintf('/tg/login/%s', (string) $telegramChatId);
            $payload = [
                'type' => 'login_success',
                'jwt' => $jwt,
            ];

            $this->magicLoginLogger->info('mercure.publish', [
                'topic' => $topic,
                'telegramChatId' => (string) $telegramChatId,
                'type' => $payload['type'],
                'hasJwt' => true,
            ]);

            $this->hub->publish(new Update($topic, json_encode($payload, JSON_THROW_ON_ERROR)));

            $this->magicLoginLogger->info('Sent Mercure login event for Telegram', [
                'chat_id' => (string) $telegramChatId,
            ]);
        }

        return $this->render('auth/magic_login_success.html.twig');
    }
}
