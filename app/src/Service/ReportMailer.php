<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ReportMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(REPORT_EMAIL)%')] private readonly string $recipient
    ) {
    }

    public function sendReport(string $pdfContent): void
    {
        $email = (new Email())
            ->to($this->recipient)
            ->subject('Weekly Feedback Report')
            ->text('Attached is the weekly feedback report in PDF format.')
            ->attach($pdfContent, 'weekly-feedback-report.pdf', 'application/pdf');

        $this->mailer->send($email);
    }
}
