<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('Dépendances manquantes : exécutez « composer install --no-dev ».');
}
require $root . '/vendor/autoload.php';

try {
    $config = App\Config::load();
} catch (RuntimeException $e) {
    http_response_code(500);
    exit(htmlspecialchars($e->getMessage()));
}

(new App\App($config, $root))
    ->handle(App\Http\Request::fromGlobals())
    ->send();
