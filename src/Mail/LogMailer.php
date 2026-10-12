<?php
declare(strict_types=1);

namespace App\Mail;

/** N'envoie rien : écrit les messages dans un fichier (développement). */
final class LogMailer implements MailerInterface
{
    public function __construct(private readonly string $file)
    {
    }

    public function send(string $toEmail, string $toName, string $subject, string $textBody, string $htmlBody): void
    {
        $entry = sprintf("=== %s\nTo: %s <%s>\nSubject: %s\n\n%s\n\n", date('c'), $toName, $toEmail, $subject, $textBody);
        if (@file_put_contents($this->file, $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new MailException('Impossible d\'écrire dans ' . $this->file);
        }
    }
}
