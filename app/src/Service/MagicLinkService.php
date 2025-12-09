<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MagicLoginToken;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MagicLinkService
{
    private string $frontendBaseUrl;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        #[Autowire(param: 'app.frontend_base_url')] string $frontendBaseUrl,
        #[Autowire(param: 'app.mailer_from')] private readonly string $mailerFrom,
        private readonly LoggerInterface $logger,
    ) {
        $this->frontendBaseUrl = rtrim($frontendBaseUrl !== '' ? $frontendBaseUrl : 'https://matchinghub.work', '/');
    }

    public function createAndSend(User $user, ?int $telegramChatId = null): void
    {
        $expiresAt = new DateTimeImmutable('+30 minutes');
        $magicLoginToken = new MagicLoginToken($user, $expiresAt, $telegramChatId);

        $this->entityManager->persist($magicLoginToken);
        $this->entityManager->flush();

        $this->logger->info('Magic login token created', [
            'magic_login_token_id' => $magicLoginToken->getId(),
            'user_id' => $user->getId(),
            'telegram_chat_id' => $telegramChatId,
        ]);

        $loginUrl = sprintf('%s/auth/magic-login/%s', $this->frontendBaseUrl, $magicLoginToken->getToken());

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Your login link')
            ->text(sprintf("Use the following link to log in: %s\n\nThis link is valid for 30 minutes.", $loginUrl));

        $this->mailer->send($email);
    }
}
