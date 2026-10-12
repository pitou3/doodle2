<?php
declare(strict_types=1);

namespace App\Mail;

final class NullMailer implements MailerInterface
{
    public function send(string $toEmail, string $toName, string $subject, string $textBody, string $htmlBody): void
    {
    }
}
