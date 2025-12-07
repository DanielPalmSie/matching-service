<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MagicLoginToken;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {
        $this->frontendBaseUrl = rtrim($frontendBaseUrl !== '' ? $frontendBaseUrl : 'https://matchinghub.work', '/');
    }

    public function createAndSend(User $user): void
    {
        $expiresAt = new DateTimeImmutable('+30 minutes');
        $magicLoginToken = new MagicLoginToken($user, $expiresAt);

        $this->entityManager->persist($magicLoginToken);
        $this->entityManager->flush();

        $loginUrl = sprintf('%s/auth/magic-login/%s', $this->frontendBaseUrl, $magicLoginToken->getToken());

        $email = (new Email())
            ->from('palm6991@gmail.com')
            ->to($user->getEmail())
            ->subject('Your login link')
            ->text(sprintf("Use the following link to log in: %s\n\nThis link is valid for 30 minutes.", $loginUrl));

        $this->mailer->send($email);
    }
}
