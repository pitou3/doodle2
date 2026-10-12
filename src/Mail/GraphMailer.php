<?php
declare(strict_types=1);

namespace App\Mail;

/**
 * Envoi via Microsoft Graph (Exchange Online) avec une application Entra ID
 * (flux « client credentials », permission d'application Mail.Send).
 */
final class GraphMailer implements MailerInterface
{
    /** @var \Closure(string $method, string $url, array $headers, string $body): array{0: int, 1: string} */
    private \Closure $http;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $sender,
        ?\Closure $http = null,
    ) {
        $this->http = $http ?? self::curl(...);
    }

    public function send(string $toEmail, string $toName, string $subject, string $textBody, string $htmlBody): void
    {
        $token = $this->accessToken();
        $payload = [
            'message' => [
                'subject' => $subject,
                'body' => ['contentType' => 'HTML', 'content' => $htmlBody],
                'toRecipients' => [['emailAddress' => ['address' => $toEmail, 'name' => $toName]]],
            ],
            'saveToSentItems' => false,
        ];
        [$status, $body] = ($this->http)(
            'POST',
            'https://graph.microsoft.com/v1.0/users/' . rawurlencode($this->sender) . '/sendMail',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        );
        if ($status !== 202) {
            throw new MailException('Graph sendMail : HTTP ' . $status . ' ' . self::errorMessage($body));
        }
    }

    private function accessToken(): string
    {
        [$status, $body] = ($this->http)(
            'POST',
            'https://login.microsoftonline.com/' . rawurlencode($this->tenantId) . '/oauth2/v2.0/token',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]),
        );
        $data = json_decode($body, true);
        if ($status !== 200 || !is_array($data) || !isset($data['access_token'])) {
            throw new MailException('Graph token : HTTP ' . $status . ' ' . self::errorMessage($body));
        }
        return (string) $data['access_token'];
    }

    private static function errorMessage(string $body): string
    {
        $data = json_decode($body, true);
        if (is_array($data) && is_array($data['error'] ?? null)) {
            return trim(($data['error']['code'] ?? '') . ' ' . ($data['error']['message'] ?? ''));
        }
        if (is_array($data) && isset($data['error'])) {
            return trim($data['error'] . ' ' . ($data['error_description'] ?? ''));
        }
        return mb_substr($body, 0, 200);
    }

    /** @return array{0: int, 1: string} */
    private static function curl(string $method, string $url, array $headers, string $body): array
    {
        if (!function_exists('curl_init')) {
            throw new MailException("L'extension PHP curl est requise pour Microsoft Graph.");
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new MailException('Graph : ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, (string) $response];
    }
}
