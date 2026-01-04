<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ReportMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(REPORT_EMAIL)%')] private readonly string $recipient,
        #[Autowire('%env(REPORT_FROM_EMAIL)%')] private readonly string $fromEmail,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendReport(string $pdfContent, ?string $recipientOverride = null): void
    {
        $recipient = $this->resolveRecipient($recipientOverride);

        $email = (new Email())
            ->from($this->fromEmail)
            ->to($recipient)
            ->subject('Weekly Feedback Report')
            ->text('Attached is the weekly feedback report in PDF format.')
            ->attach($pdfContent, 'weekly-feedback-report.pdf', 'application/pdf');

        $this->mailer->send($email);
    }

    public function resolveRecipient(?string $recipientOverride = null): string
    {
        return $recipientOverride ?: $this->recipient;
    }
}
