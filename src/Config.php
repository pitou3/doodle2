<?php
declare(strict_types=1);

namespace App;

final class Config
{
    public static function load(?string $file = null): array
    {
        $file ??= getenv('APP_CONFIG') ?: dirname(__DIR__) . '/config/config.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Fichier de configuration introuvable : ' . $file
                . ' (copiez config/config.example.php en config/config.php)');
        }
        $config = require $file;
        if (!is_array($config)) {
            throw new \RuntimeException('Le fichier de configuration doit retourner un tableau.');
        }
        return array_replace_recursive(self::defaults(), $config);
    }

    public static function defaults(): array
    {
        return [
            'app' => [
                'name' => 'Sondages',
                'base_url' => 'http://localhost',
                'timezone' => 'Europe/Zurich',
                'default_lang' => 'fr',
                'retention_months' => 6,
                'cron_key' => '',
                'setup_token' => '',
                'debug' => false,
            ],
            'db' => [
                'host' => 'localhost',
                'port' => 3306,
                'name' => '',
                'user' => '',
                'password' => '',
            ],
            'mail' => [
                'driver' => 'none',
                'from_email' => '',
                'from_name' => '',
                'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''],
                'graph' => ['tenant_id' => '', 'client_id' => '', 'client_secret' => '', 'sender' => ''],
            ],
        ];
    }
}
