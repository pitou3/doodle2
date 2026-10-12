<?php
// Supprime les sondages dont la période d'archivage est écoulée : php bin/cron.php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$app = new App\App(App\Config::load(), $root);
$count = $app->runMaintenance();
echo date('c') . " – $count sondage(s) supprimé(s)\n";
