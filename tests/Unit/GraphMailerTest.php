<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Mail\GraphMailer;
use App\Mail\MailException;
use PHPUnit\Framework\TestCase;

final class GraphMailerTest extends TestCase
{
    public function testSendsMailWithClientCredentialsToken(): void
    {
        $calls = [];
        $http = function (string $method, string $url, array $headers, string $body) use (&$calls): array {
            $calls[] = compact('method', 'url', 'headers', 'body');
            if (str_contains($url, 'login.microsoftonline.com')) {
                return [200, json_encode(['access_token' => 'tok-123', 'expires_in' => 3599])];
            }
            return [202, ''];
        };
        $mailer = new GraphMailer('tenant-id', 'client-id', 's3cret', 'sondages@bcswing.com', $http);
        $mailer->send('alice@example.com', 'Alice', 'Sujet é', 'texte', '<p>html</p>');

        self::assertCount(2, $calls);
        self::assertSame('https://login.microsoftonline.com/tenant-id/oauth2/v2.0/token', $calls[0]['url']);
        parse_str($calls[0]['body'], $form);
        self::assertSame('client_credentials', $form['grant_type']);
        self::assertSame('https://graph.microsoft.com/.default', $form['scope']);
        self::assertSame('s3cret', $form['client_secret']);

        self::assertSame('https://graph.microsoft.com/v1.0/users/sondages%40bcswing.com/sendMail', $calls[1]['url']);
        self::assertContains('Authorization: Bearer tok-123', $calls[1]['headers']);
        $payload = json_decode($calls[1]['body'], true);
        self::assertSame('Sujet é', $payload['message']['subject']);
        self::assertSame('HTML', $payload['message']['body']['contentType']);
        self::assertSame('alice@example.com', $payload['message']['toRecipients'][0]['emailAddress']['address']);
    }

    public function testErrorsAreReported(): void
    {
        $http = static function (string $method, string $url): array {
            if (str_contains($url, 'login.microsoftonline.com')) {
                return [200, '{"access_token":"t"}'];
            }
            return [403, '{"error":{"code":"ErrorAccessDenied","message":"Access is denied."}}'];
        };
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Access is denied.');
        (new GraphMailer('t', 'c', 's', 'x@y.z', $http))->send('a@b.c', 'A', 's', 't', 'h');
    }

    public function testTokenFailure(): void
    {
        $http = static fn (): array => [401, '{"error":"invalid_client","error_description":"AADSTS7000215: Invalid client secret"}'];
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('invalid_client');
        (new GraphMailer('t', 'c', 's', 'x@y.z', $http))->send('a@b.c', 'A', 's', 't', 'h');
    }
}
