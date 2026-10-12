<?php
declare(strict_types=1);

namespace App\Mail;

interface MailerInterface
{
    /** @throws MailException */
    public function send(string $toEmail, string $toName, string $subject, string $textBody, string $htmlBody): void;
}
