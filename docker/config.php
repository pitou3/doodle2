<?php
// Configuration utilisée par l'environnement Docker (développement / tests).

$env = static function (string $key, $default = null) {
    $value = getenv($key);
    return $value === false ? $default : $value;
};

return [
    'app' => [
        'name' => 'BC Swing – Sondages (dev)',
        'base_url' => $env('APP_URL', 'http://localhost:8080'),
        'timezone' => 'Europe/Zurich',
        'default_lang' => 'fr',
        'retention_months' => 6,
        'cron_key' => 'dev-cron-key',
        'setup_token' => 'dev-setup-token',
        'debug' => true,
    ],
    'db' => [
        'host' => $env('DB_HOST', 'db'),
        'port' => (int) $env('DB_PORT', 3306),
        'name' => $env('DB_NAME', 'doodle2'),
        'user' => $env('DB_USER', 'doodle2'),
        'password' => $env('DB_PASSWORD', 'doodle2'),
    ],
    'mail' => [
        'driver' => $env('MAIL_DRIVER', 'smtp'),
        'from_email' => 'sondages@bcswing.test',
        'from_name' => 'BC Swing – Sondages',
        'smtp' => [
            'host' => $env('SMTP_HOST', 'mailpit'),
            'port' => (int) $env('SMTP_PORT', 1025),
            'encryption' => '',
            'username' => '',
            'password' => '',
        ],
        'graph' => [
            'tenant_id' => $env('GRAPH_TENANT_ID', ''),
            'client_id' => $env('GRAPH_CLIENT_ID', ''),
            'client_secret' => $env('GRAPH_CLIENT_SECRET', ''),
            'sender' => $env('GRAPH_SENDER', ''),
        ],
    ],
];
