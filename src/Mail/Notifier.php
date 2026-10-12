<?php
declare(strict_types=1);

namespace App\Mail;

use App\I18n;

/** Compose les emails envoyés par l'application. */
final class Notifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly I18n $i18n,
        private readonly string $appName,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * Envoie au créateur ses liens (public et administration).
     * Retourne false si aucun email n'a été fourni. Lève MailException en cas d'échec.
     */
    public function pollCreated(array $poll, string $adminToken): bool
    {
        if (empty($poll['author_email'])) {
            return false;
        }
        $i18n = $this->i18n->forLang((string) $poll['lang']);
        $publicUrl = $this->baseUrl . '/p/' . $poll['public_id'];
        $adminUrl = $this->baseUrl . '/a/' . $poll['public_id'] . '/' . $adminToken;
        $vars = ['name' => $poll['author_name'], 'title' => $poll['title'], 'app' => $this->appName];

        $subject = $i18n->t('mail.created.subject', $vars);
        $text = implode("\n", [
            $i18n->t('mail.hello', $vars),
            '',
            $i18n->t('mail.created.intro', $vars),
            '',
            $i18n->t('mail.created.public_label'),
            $publicUrl,
            '',
            $i18n->t('mail.created.admin_label'),
            $adminUrl,
            '',
            $i18n->t('mail.created.keep_secret'),
            '',
            '-- ',
            $this->appName,
        ]);

        $h = static fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html><body style="font-family:Arial,Helvetica,sans-serif;color:#1f2933;line-height:1.5">'
            . '<p>' . $h($i18n->t('mail.hello', $vars)) . '</p>'
            . '<p>' . $h($i18n->t('mail.created.intro', $vars)) . '</p>'
            . '<p><strong>' . $h($i18n->t('mail.created.public_label')) . '</strong><br>'
            . '<a href="' . $h($publicUrl) . '">' . $h($publicUrl) . '</a></p>'
            . '<p><strong>' . $h($i18n->t('mail.created.admin_label')) . '</strong><br>'
            . '<a href="' . $h($adminUrl) . '">' . $h($adminUrl) . '</a></p>'
            . '<p style="color:#b42318">' . $h($i18n->t('mail.created.keep_secret')) . '</p>'
            . '<p style="color:#6b7280">— ' . $h($this->appName) . '</p>'
            . '</body></html>';

        $this->mailer->send((string) $poll['author_email'], (string) $poll['author_name'], $subject, $text, $html);
        return true;
    }
}
