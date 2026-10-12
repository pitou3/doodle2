<?php
declare(strict_types=1);

namespace App;

use PDO;

/** Applique les fichiers migrations/*.sql qui ne l'ont pas encore été. */
final class Migrator
{
    public function __construct(private readonly PDO $db, private readonly string $dir)
    {
    }

    /** @return list<string> versions appliquées lors de cet appel */
    public function migrate(): array
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $applied = $this->db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files);

        $done = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $applied, true)) {
                continue;
            }
            foreach (self::statements((string) file_get_contents($file)) as $statement) {
                $this->db->exec($statement);
            }
            $this->db->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                ->execute([$version, date('Y-m-d H:i:s')]);
            $done[] = $version;
        }
        return $done;
    }

    /** @return list<string> */
    public static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        $parts = preg_split('/;\s*(?:\R|$)/', $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $s) => $s !== ''));
    }
}
