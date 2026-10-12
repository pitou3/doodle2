<?php
declare(strict_types=1);

namespace App\Mail;

final class MailerFactory
{
    public static function create(array $mail, string $logDir): MailerInterface
    {
        return match ($mail['driver'] ?? 'none') {
            'smtp' => new SmtpMailer($mail['smtp'], (string) $mail['from_email'], (string) $mail['from_name']),
            'graph' => new GraphMailer(
                (string) $mail['graph']['tenant_id'],
                (string) $mail['graph']['client_id'],
                (string) $mail['graph']['client_secret'],
                (string) ($mail['graph']['sender'] ?: $mail['from_email']),
            ),
            'log' => new LogMailer($logDir . '/mail.log'),
            'none' => new NullMailer(),
            default => throw new \InvalidArgumentException('mail.driver inconnu : ' . $mail['driver']),
        };
    }
}
