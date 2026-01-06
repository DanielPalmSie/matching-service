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
        #[Autowire('%env(REPLY_TO_EMAIL)%')] private readonly string $replyToEmail,
        #[Autowire('%env(LIST_UNSUBSCRIBE_EMAIL)%')] private readonly string $listUnsubscribeEmail,
        #[Autowire('%env(LIST_UNSUBSCRIBE_URL)%')] private readonly string $listUnsubscribeUrl,
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
            ->replyTo($this->replyToEmail)
            ->subject('Weekly Feedback Report')
            ->text('Attached is the weekly feedback report in PDF format.')
            ->html('<p>Attached is the weekly feedback report in PDF format.</p>')
            ->attach($pdfContent, 'weekly-feedback-report.pdf', 'application/pdf');

        $headers = $email->getHeaders();
        $headers->addTextHeader(
            'List-Unsubscribe',
            sprintf('<mailto:%s>, <%s>', $this->listUnsubscribeEmail, $this->listUnsubscribeUrl)
        );
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->mailer->send($email);
    }

    public function resolveRecipient(?string $recipientOverride = null): string
    {
        return $recipientOverride ?: $this->recipient;
    }
}
