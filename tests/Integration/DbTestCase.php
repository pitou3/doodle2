<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Database;
use App\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/** Tests sur une vraie base MariaDB (base doodle2_test du docker-compose). */
abstract class DbTestCase extends TestCase
{
    private static ?PDO $pdo = null;

    protected static function pdo(): PDO
    {
        if (self::$pdo === null) {
            try {
                $pdo = Database::connect([
                    'host' => getenv('DB_HOST') ?: 'db',
                    'port' => 3306,
                    'name' => getenv('TEST_DB_NAME') ?: 'doodle2_test',
                    'user' => getenv('DB_USER') ?: 'doodle2',
                    'password' => getenv('DB_PASSWORD') ?: 'doodle2',
                ]);
            } catch (\PDOException $e) {
                self::markTestSkipped('Base de test indisponible : ' . $e->getMessage());
            }
            // Schéma recréé à chaque exécution de la suite.
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                $pdo->exec('DROP TABLE `' . $table . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            (new Migrator($pdo, dirname(__DIR__, 2) . '/migrations'))->migrate();
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    protected function setUp(): void
    {
        $pdo = self::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['vote_choices', 'votes', 'poll_options', 'polls', 'admins', 'login_attempts'] as $table) {
            $pdo->exec('TRUNCATE TABLE ' . $table);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
