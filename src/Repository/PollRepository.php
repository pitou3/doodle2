<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class PollRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function transaction(callable $fn): mixed
    {
        if ($this->db->inTransaction()) {
            return $fn();
        }
        $this->db->beginTransaction();
        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // --- Sondages -----------------------------------------------------------

    public function findById(int $id): ?array
    {
        return $this->one('SELECT * FROM polls WHERE id = ?', [$id]);
    }

    public function findByPublicId(string $publicId): ?array
    {
        if (!preg_match('/^[a-z0-9]{8,32}$/', $publicId)) {
            return null;
        }
        return $this->one('SELECT * FROM polls WHERE public_id = ?', [$publicId]);
    }

    public function insertPoll(array $data): int
    {
        $columns = array_keys($data);
        $sql = 'INSERT INTO polls (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $this->db->prepare($sql)->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function updatePoll(int $id, array $data): void
    {
        $sets = implode(', ', array_map(static fn (string $c) => $c . ' = ?', array_keys($data)));
        $this->db->prepare('UPDATE polls SET ' . $sets . ' WHERE id = ?')
            ->execute([...array_values($data), $id]);
    }

    public function touch(int $id, string $now): void
    {
        $this->db->prepare('UPDATE polls SET last_activity_at = ? WHERE id = ?')->execute([$now, $id]);
    }

    public function deletePoll(int $id): void
    {
        $this->db->prepare('DELETE FROM polls WHERE id = ?')->execute([$id]);
    }

    /**
     * Supprime les sondages dont la période d'archivage est terminée.
     * Clôturés : ends_at antérieur à $cutoff. Sans fin définie : inactifs depuis $cutoff.
     */
    public function purge(string $cutoff): int
    {
        $stmt = $this->db->prepare('DELETE FROM polls
            WHERE (ends_at IS NOT NULL AND ends_at < ?)
               OR (ends_at IS NULL AND last_activity_at < ?)');
        $stmt->execute([$cutoff, $cutoff]);
        return $stmt->rowCount();
    }

    /** @return array{rows: list<array>, total: int} */
    public function search(string $q, string $status, string $now, int $limit, int $offset): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(p.title LIKE ? OR p.author_name LIKE ? OR p.author_email LIKE ? OR p.public_id = ?)';
            array_push($params, $like, $like, $like, $q);
        }
        if ($status === 'open') {
            $where[] = '(p.ends_at IS NULL OR p.ends_at > ?)';
            $params[] = $now;
        } elseif ($status === 'archived') {
            $where[] = '(p.ends_at IS NOT NULL AND p.ends_at <= ?)';
            $params[] = $now;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $count = $this->db->prepare('SELECT COUNT(*) FROM polls p' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->db->prepare('SELECT p.*,
                (SELECT COUNT(*) FROM votes v WHERE v.poll_id = p.id) AS vote_count
            FROM polls p' . $whereSql . '
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset));
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    // --- Options ------------------------------------------------------------

    public function options(int $pollId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM poll_options WHERE poll_id = ? ORDER BY option_date, position, id');
        $stmt->execute([$pollId]);
        return $stmt->fetchAll();
    }

    public function insertOption(int $pollId, ?string $date, string $label, int $position): int
    {
        $this->db->prepare('INSERT INTO poll_options (poll_id, option_date, label, position) VALUES (?, ?, ?, ?)')
            ->execute([$pollId, $date, $label, $position]);
        return (int) $this->db->lastInsertId();
    }

    public function updateOption(int $id, ?string $date, string $label): void
    {
        $this->db->prepare('UPDATE poll_options SET option_date = ?, label = ? WHERE id = ?')
            ->execute([$date, $label, $id]);
    }

    public function deleteOption(int $id): void
    {
        $this->db->prepare('DELETE FROM poll_options WHERE id = ?')->execute([$id]);
    }

    public function maxPosition(int $pollId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(position), -1) FROM poll_options WHERE poll_id = ?');
        $stmt->execute([$pollId]);
        return (int) $stmt->fetchColumn();
    }

    // --- Votes --------------------------------------------------------------

    /** @return list<array> chaque vote contient 'choices' => [option_id => valeur] */
    public function votes(int $pollId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM votes WHERE poll_id = ? ORDER BY created_at, id');
        $stmt->execute([$pollId]);
        $votes = [];
        foreach ($stmt->fetchAll() as $vote) {
            $vote['choices'] = [];
            $votes[(int) $vote['id']] = $vote;
        }

        $stmt = $this->db->prepare('SELECT c.vote_id, c.option_id, c.value
            FROM vote_choices c JOIN votes v ON v.id = c.vote_id WHERE v.poll_id = ?');
        $stmt->execute([$pollId]);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($votes[(int) $row['vote_id']])) {
                $votes[(int) $row['vote_id']]['choices'][(int) $row['option_id']] = (int) $row['value'];
            }
        }
        return array_values($votes);
    }

    public function findVote(int $id): ?array
    {
        return $this->one('SELECT * FROM votes WHERE id = ?', [$id]);
    }

    public function countVotes(int $pollId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM votes WHERE poll_id = ?');
        $stmt->execute([$pollId]);
        return (int) $stmt->fetchColumn();
    }

    public function insertVote(int $pollId, string $name, string $tokenHash, array $choices, string $now): int
    {
        $this->db->prepare('INSERT INTO votes (poll_id, name, edit_token_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$pollId, $name, $tokenHash, $now, $now]);
        $id = (int) $this->db->lastInsertId();
        $this->insertChoices($id, $choices);
        return $id;
    }

    public function updateVote(int $id, string $name, array $choices, string $now): void
    {
        $this->db->prepare('UPDATE votes SET name = ?, updated_at = ? WHERE id = ?')->execute([$name, $now, $id]);
        $this->db->prepare('DELETE FROM vote_choices WHERE vote_id = ?')->execute([$id]);
        $this->insertChoices($id, $choices);
    }

    public function deleteVote(int $id): void
    {
        $this->db->prepare('DELETE FROM votes WHERE id = ?')->execute([$id]);
    }

    /** La collation utf8mb4_unicode_ci rend la comparaison insensible à la casse et aux accents. */
    public function nameExists(int $pollId, string $name, ?int $exceptVoteId = null): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM votes WHERE poll_id = ? AND name = ? AND id <> ? LIMIT 1');
        $stmt->execute([$pollId, $name, $exceptVoteId ?? 0]);
        return (bool) $stmt->fetchColumn();
    }

    private function insertChoices(int $voteId, array $choices): void
    {
        $stmt = $this->db->prepare('INSERT INTO vote_choices (vote_id, option_id, value) VALUES (?, ?, ?)');
        foreach ($choices as $optionId => $value) {
            $stmt->execute([$voteId, (int) $optionId, (int) $value]);
        }
    }

    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
