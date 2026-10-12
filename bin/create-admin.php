<?php
// Crée un administrateur ou réinitialise son mot de passe : php bin/create-admin.php <identifiant>
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$username = $argv[1] ?? '';
if (!preg_match('/^[\p{L}0-9._@-]{3,60}$/u', $username)) {
    fwrite(STDERR, "Usage : php bin/create-admin.php <identifiant>\n");
    exit(1);
}

$password = getenv('ADMIN_PASSWORD') ?: '';
if ($password === '') {
    echo 'Mot de passe (10 caractères minimum) : ';
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
    }
    $password = trim((string) fgets(STDIN));
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty echo');
    }
    echo "\n";
}
if (mb_strlen($password) < 10) {
    fwrite(STDERR, "Mot de passe trop court.\n");
    exit(1);
}

$config = App\Config::load();
date_default_timezone_set($config['app']['timezone']);
$admins = new App\Repository\AdminRepository(App\Database::connect($config['db']));
$hash = password_hash($password, PASSWORD_DEFAULT);
$existing = $admins->findByUsername($username);
if ($existing) {
    $admins->updatePassword((int) $existing['id'], $hash);
    echo "Mot de passe de « $username » mis à jour.\n";
} else {
    $admins->create($username, $hash, date('Y-m-d H:i:s'));
    echo "Administrateur « $username » créé.\n";
}
