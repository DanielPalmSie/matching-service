<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailConfirmationToken;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class RegistrationEmailService
{
    private string $frontendBaseUrl;

    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(param: 'app.frontend_base_url')] string $frontendBaseUrl,
        #[Autowire(param: 'app.mailer_from')] private readonly string $mailerFrom,
    ) {
        $this->frontendBaseUrl = rtrim($frontendBaseUrl !== '' ? $frontendBaseUrl : 'https://matchinghub.work', '/');
    }

    public function sendConfirmationEmail(User $user, EmailConfirmationToken $token): void
    {
        $confirmationUrl = sprintf('%s/auth/confirm-email/%s', $this->frontendBaseUrl, $token->getToken());

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'Matching Hub'))
            ->to(new Address($user->getEmail()))
            ->subject('Confirm your email address')
            ->text(sprintf('Please confirm your email by visiting: %s', $confirmationUrl))
            ->htmlTemplate('email/registration_confirmation.html.twig')
            ->context([
                'confirmationUrl' => $confirmationUrl,
                'expiresAt' => $token->getExpiresAt(),
                'userEmail' => $user->getEmail(),
            ]);

        $this->mailer->send($email);
    }
}
