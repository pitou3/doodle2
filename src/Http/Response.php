<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    /** @var list<array{name: string, value: string, options: array}> */
    private array $cookies = [];

    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withCookie(string $name, string $value, int $expires, string $path, bool $secure, bool $httpOnly = true): self
    {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'options' => [
                'expires' => $expires,
                'path' => $path,
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => 'Lax',
            ],
        ];
        return $this;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        foreach ($this->cookies as $cookie) {
            setcookie($cookie['name'], $cookie['value'], $cookie['options']);
        }
        echo $this->body;
    }
}
