<?php
declare(strict_types=1);

namespace Tests\E2E;

/** Mini client HTTP avec cookies, simulant un navigateur. */
final class HttpClient
{
    private \CurlHandle $ch;
    public int $status = 0;
    public array $headers = [];
    public string $body = '';

    public function __construct(private readonly string $baseUrl, private readonly string $lang = 'fr')
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, ''); // moteur de cookies en mémoire
    }

    public function get(string $path): self
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $data, bool $withCsrf = true): self
    {
        if ($withCsrf && !isset($data['_csrf'])) {
            $data['_csrf'] = $this->csrf();
        }
        return $this->request('POST', $path, http_build_query($data));
    }

    public function csrf(): string
    {
        if (!preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->body, $m)) {
            throw new \RuntimeException('Jeton CSRF introuvable dans la dernière page');
        }
        return $m[1];
    }

    public function location(): string
    {
        return $this->headers['location'] ?? '';
    }

    /** Suit la redirection courante (chemin relatif à la racine). */
    public function follow(): self
    {
        $location = $this->location();
        $path = (string) parse_url($location, PHP_URL_PATH);
        $query = parse_url($location, PHP_URL_QUERY);
        return $this->get($path . ($query ? '?' . $query : ''));
    }

    public function cookies(): array
    {
        $cookies = [];
        foreach (curl_getinfo($this->ch, CURLINFO_COOKIELIST) as $line) {
            $parts = explode("\t", $line);
            $cookies[$parts[5]] = urldecode($parts[6] ?? '');
        }
        return $cookies;
    }

    private function request(string $method, string $path, ?string $body = null): self
    {
        $this->headers = [];
        curl_setopt_array($this->ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POST => $method === 'POST',
            CURLOPT_POSTFIELDS => $body ?? '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept-Language: ' . ($this->lang === 'en' ? 'en-US,en;q=0.9' : 'fr-CH,fr;q=0.9')],
            CURLOPT_HEADERFUNCTION => function ($ch, string $header): int {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $this->headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($header);
            },
        ]);
        if ($method === 'GET') {
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        }
        $this->body = (string) curl_exec($this->ch);
        $this->status = (int) curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        return $this;
    }
}
