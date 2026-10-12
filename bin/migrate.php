<?php
// Crée / met à jour les tables : php bin/migrate.php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$config = App\Config::load();
$pdo = App\Database::connect($config['db']);
$applied = (new App\Migrator($pdo, dirname(__DIR__) . '/migrations'))->migrate();

echo $applied ? 'Migrations appliquées : ' . implode(', ', $applied) . "\n" : "Base déjà à jour.\n";
