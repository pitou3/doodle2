<?php
declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class SmtpMailer implements MailerInterface
{
    public function __construct(
        private readonly array $smtp,
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {
    }

    public function send(string $toEmail, string $toName, string $subject, string $textBody, string $htmlBody): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) $this->smtp['host'];
            $mail->Port = (int) $this->smtp['port'];
            $mail->Timeout = 20;
            $encryption = (string) ($this->smtp['encryption'] ?? '');
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $username = (string) ($this->smtp['username'] ?? '');
            if ($username !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = (string) ($this->smtp['password'] ?? '');
            }

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new MailException('SMTP : ' . $e->getMessage(), 0, $e);
        }
    }
}
