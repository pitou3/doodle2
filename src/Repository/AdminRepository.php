<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class AdminRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }

    public function all(): array
    {
        return $this->db->query('SELECT id, username, created_at, last_login_at FROM admins ORDER BY username')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admins WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $username, string $passwordHash, string $now): int
    {
        $this->db->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$username, $passwordHash, $now]);
        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $id]);
    }

    public function touchLogin(int $id, string $now): void
    {
        $this->db->prepare('UPDATE admins SET last_login_at = ? WHERE id = ?')->execute([$now, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
    }

    public function recordFailedLogin(string $ip, string $username, string $now): void
    {
        $this->db->prepare('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, ?)')
            ->execute([$ip, mb_substr($username, 0, 60), $now]);
    }

    public function recentFailures(string $ip, string $since): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?');
        $stmt->execute([$ip, $since]);
        return (int) $stmt->fetchColumn();
    }

    public function clearFailures(string $ip): void
    {
        $this->db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    }

    public function purgeAttempts(string $before): void
    {
        $this->db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$before]);
    }
}
