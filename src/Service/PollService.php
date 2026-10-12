<?php
declare(strict_types=1);

namespace App\Service;

use App\Http\HttpException;
use App\Repository\PollRepository;
use App\Security;
use DateTimeImmutable;

/**
 * Règles métier des sondages.
 *
 * Cycle de vie : un sondage est ouvert jusqu'à sa « fin » (ends_at), qui est la plus proche de :
 * la clôture manuelle, la date limite, ou — pour un sondage de dates sans date limite — la fin
 * de la dernière date proposée. Il est ensuite archivé (lecture seule) puis supprimé
 * `retentionMonths` mois plus tard. Un sondage classique sans date limite reste ouvert et est
 * supprimé après `retentionMonths` mois sans activité.
 */
final class PollService
{
    public const TYPES = ['date', 'classic'];
    public const POLICIES = ['all', 'own', 'none'];

    public const NO = 0;
    public const MAYBE = 1;
    public const YES = 2;

    public const MAX_OPTIONS = 100;

    public function __construct(
        private readonly PollRepository $repo,
        private readonly int $retentionMonths = 6,
        private ?DateTimeImmutable $fixedNow = null,
    ) {
    }

    /** Permet aux tests de figer l'heure courante. */
    public function setNow(?DateTimeImmutable $now): void
    {
        $this->fixedNow = $now;
    }

    public function now(): DateTimeImmutable
    {
        return $this->fixedNow ?? new DateTimeImmutable();
    }

    private function nowString(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    // --- Création et modification ------------------------------------------

    /** @return array{poll: array, admin_token: string} */
    public function create(string $type, array $input, string $lang): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new HttpException(404, 'error.not_found');
        }
        $errors = [];
        $fields = $this->validateFields($input, $errors, true);
        $options = $type === 'date'
            ? $this->parseDateRows($input['dates'] ?? [], $errors)
            : $this->parseClassicRows($input['options'] ?? [], $errors);
        if ($options === [] && !isset($errors['options'])) {
            $errors['options'] = 'error.options_required';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $adminToken = Security::randomToken(32);
        $now = $this->nowString();
        $pollId = $this->repo->transaction(function () use ($type, $fields, $options, $adminToken, $now, $lang): int {
            $id = $this->repo->insertPoll($fields + [
                'public_id' => Security::randomToken(16),
                'admin_token_hash' => Security::hash($adminToken),
                'type' => $type,
                'lang' => $lang,
                'created_at' => $now,
                'updated_at' => $now,
                'last_activity_at' => $now,
            ]);
            foreach ($options as $position => $option) {
                $this->repo->insertOption($id, $option['date'], $option['label'], $position);
            }
            return $id;
        });
        $this->refreshEndsAt($pollId);

        return ['poll' => $this->repo->findById($pollId), 'admin_token' => $adminToken];
    }

    public function updateSettings(array $poll, array $input): void
    {
        $errors = [];
        $fields = $this->validateFields($input, $errors, false);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $now = $this->nowString();
        $fields['closed_at'] = !empty($input['closed']) ? ($poll['closed_at'] ?? $now) : null;
        $fields['updated_at'] = $now;
        $fields['last_activity_at'] = $now;
        $this->repo->updatePoll((int) $poll['id'], $fields);
        $this->refreshEndsAt((int) $poll['id']);
    }

    /**
     * $input['existing'][option_id] = ['date' => …, 'label' => …, 'delete' => 1]
     * Nouveaux choix : $input['dates'] (sondage de dates) ou $input['options'] (classique).
     */
    public function updateOptions(array $poll, array $input): void
    {
        $pollId = (int) $poll['id'];
        $isDate = $poll['type'] === 'date';
        $errors = [];
        $current = $this->repo->options($pollId);
        $existing = is_array($input['existing'] ?? null) ? $input['existing'] : [];

        $updates = [];
        $deletes = [];
        foreach ($current as $option) {
            $id = (int) $option['id'];
            $row = $existing[$id] ?? null;
            if (!is_array($row)) {
                continue;
            }
            if (!empty($row['delete'])) {
                $deletes[] = $id;
                continue;
            }
            $label = $this->clean($row['label'] ?? '');
            if ($isDate) {
                $date = $this->clean($row['date'] ?? '');
                if (!$this->isValidDate($date)) {
                    $errors['options'] = 'error.invalid_date';
                    continue;
                }
                if (mb_strlen($label) > 50) {
                    $errors['options'] = 'error.too_long';
                    continue;
                }
            } else {
                $date = null;
                if ($label === '') {
                    $errors['options'] = 'error.option_empty';
                    continue;
                }
                if (mb_strlen($label) > 255) {
                    $errors['options'] = 'error.too_long';
                    continue;
                }
            }
            $updates[$id] = ['date' => $date, 'label' => $label];
        }

        $new = $isDate
            ? $this->parseDateRows($input['dates'] ?? [], $errors)
            : $this->parseClassicRows($input['options'] ?? [], $errors);

        $remaining = count($current) - count($deletes) + count($new);
        if ($remaining < 1) {
            $errors['options'] = 'error.options_required';
        } elseif ($remaining > self::MAX_OPTIONS) {
            $errors['options'] = 'error.too_many_options';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->repo->transaction(function () use ($pollId, $updates, $deletes, $new): void {
            foreach ($updates as $id => $u) {
                $this->repo->updateOption($id, $u['date'], $u['label']);
            }
            foreach ($deletes as $id) {
                $this->repo->deleteOption($id);
            }
            $position = $this->repo->maxPosition($pollId) + 1;
            foreach ($new as $option) {
                $this->repo->insertOption($pollId, $option['date'], $option['label'], $position++);
            }
        });
        $this->repo->touch($pollId, $this->nowString());
        $this->refreshEndsAt($pollId);
    }

    public function delete(array $poll): void
    {
        $this->repo->deletePoll((int) $poll['id']);
    }

    // --- Votes --------------------------------------------------------------

    /** @return array{vote_id: int, token: ?string} jeton renvoyé uniquement à la création */
    public function submitVote(array $poll, array $input, AccessContext $ctx): array
    {
        $voteId = isset($input['vote_id']) && is_scalar($input['vote_id']) ? (int) $input['vote_id'] : 0;
        $vote = null;
        if ($voteId > 0) {
            $vote = $this->findPollVote($poll, $voteId);
            if (!$this->canEditVote($poll, $vote, $ctx)) {
                throw new HttpException(403, $this->isOpen($poll) ? 'error.cannot_edit_vote' : 'error.poll_closed');
            }
        } elseif (!$this->canAddVote($poll, $ctx)) {
            throw new HttpException(403, 'error.poll_closed');
        }

        $errors = [];
        $name = $this->clean($input['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'error.required';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'error.too_long';
        } elseif ($this->repo->nameExists((int) $poll['id'], $name, $vote ? (int) $vote['id'] : null)) {
            $errors['name'] = 'error.name_taken';
        }

        $raw = is_array($input['choices'] ?? null) ? $input['choices'] : [];
        $choices = [];
        foreach ($this->repo->options((int) $poll['id']) as $option) {
            $value = $raw[$option['id']] ?? self::NO;
            $value = is_scalar($value) && is_numeric($value) ? (int) $value : -1;
            $allowed = $poll['allow_maybe'] ? [self::NO, self::MAYBE, self::YES] : [self::NO, self::YES];
            if (!in_array($value, $allowed, true)) {
                $errors['choices'] = 'error.invalid_value';
                $value = self::NO;
            }
            $choices[(int) $option['id']] = $value;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = $this->nowString();
        $token = null;
        if ($vote === null) {
            $token = Security::randomToken(24);
            $voteId = (int) $this->repo->transaction(
                fn () => $this->repo->insertVote((int) $poll['id'], $name, Security::hash($token), $choices, $now)
            );
        } else {
            $this->repo->transaction(fn () => $this->repo->updateVote($voteId, $name, $choices, $now));
        }
        $this->repo->touch((int) $poll['id'], $now);

        return ['vote_id' => $voteId, 'token' => $token];
    }

    public function deleteVote(array $poll, int $voteId, AccessContext $ctx): void
    {
        $vote = $this->findPollVote($poll, $voteId);
        if (!$this->canEditVote($poll, $vote, $ctx)) {
            throw new HttpException(403, 'error.cannot_edit_vote');
        }
        $this->repo->deleteVote($voteId);
        $this->repo->touch((int) $poll['id'], $this->nowString());
    }

    /** Vérifie un lien personnel de modification. */
    public function voteMatchesToken(array $poll, int $voteId, string $token): bool
    {
        $vote = $this->repo->findVote($voteId);
        return $vote !== null
            && (int) $vote['poll_id'] === (int) $poll['id']
            && hash_equals((string) $vote['edit_token_hash'], Security::hash($token));
    }

    private function findPollVote(array $poll, int $voteId): array
    {
        $vote = $this->repo->findVote($voteId);
        if ($vote === null || (int) $vote['poll_id'] !== (int) $poll['id']) {
            throw new HttpException(404, 'error.vote_not_found');
        }
        return $vote;
    }

    // --- Permissions et état -----------------------------------------------

    public function isOpen(array $poll): bool
    {
        return $poll['ends_at'] === null || $poll['ends_at'] > $this->nowString();
    }

    public function canAddVote(array $poll, AccessContext $ctx): bool
    {
        return $ctx->manager || $this->isOpen($poll);
    }

    /** Le créateur et les administrateurs peuvent toujours modifier ; les votants selon la règle choisie. */
    public function canEditVote(array $poll, array $vote, AccessContext $ctx): bool
    {
        if ($ctx->manager) {
            return true;
        }
        if (!$this->isOpen($poll)) {
            return false;
        }
        return match ($poll['edit_policy']) {
            'all' => true,
            'own' => $ctx->owns($vote),
            default => false,
        };
    }

    /** Date à laquelle le sondage sera supprimé définitivement. */
    public function purgeDate(array $poll): DateTimeImmutable
    {
        $base = $poll['ends_at'] ?? $poll['last_activity_at'];
        return (new DateTimeImmutable($base))->modify('+' . $this->retentionMonths . ' months');
    }

    public function computeEndsAt(array $poll, array $options): ?string
    {
        $candidates = array_values(array_filter([$poll['closed_at'] ?? null, $poll['deadline'] ?? null]));
        if ($candidates === [] && $poll['type'] === 'date') {
            $dates = array_filter(array_column($options, 'option_date'));
            if ($dates !== []) {
                $candidates[] = max($dates) . ' 23:59:59';
            }
        }
        return $candidates === [] ? null : min($candidates);
    }

    public function refreshEndsAt(int $pollId): void
    {
        $poll = $this->repo->findById($pollId);
        if ($poll !== null) {
            $this->repo->updatePoll($pollId, ['ends_at' => $this->computeEndsAt($poll, $this->repo->options($pollId))]);
        }
    }

    public function purgeExpired(): int
    {
        $cutoff = $this->now()->modify('-' . $this->retentionMonths . ' months')->format('Y-m-d H:i:s');
        return $this->repo->purge($cutoff);
    }

    // --- Résultats ----------------------------------------------------------

    /**
     * @return array<int, array{yes: int, maybe: int, yes_names: list<string>, maybe_names: list<string>, best: bool}>
     */
    public function results(array $options, array $votes): array
    {
        $results = [];
        foreach ($options as $option) {
            $results[(int) $option['id']] = ['yes' => 0, 'maybe' => 0, 'yes_names' => [], 'maybe_names' => [], 'best' => false];
        }
        foreach ($votes as $vote) {
            foreach ($vote['choices'] as $optionId => $value) {
                if (!isset($results[$optionId])) {
                    continue;
                }
                if ($value === self::YES) {
                    $results[$optionId]['yes']++;
                    $results[$optionId]['yes_names'][] = $vote['name'];
                } elseif ($value === self::MAYBE) {
                    $results[$optionId]['maybe']++;
                    $results[$optionId]['maybe_names'][] = $vote['name'];
                }
            }
        }
        $best = null;
        foreach ($results as $r) {
            $score = [$r['yes'], $r['maybe']];
            if ($r['yes'] + $r['maybe'] > 0 && ($best === null || $score > $best)) {
                $best = $score;
            }
        }
        foreach ($results as &$r) {
            $r['best'] = $best !== null && [$r['yes'], $r['maybe']] === $best;
        }
        return $results;
    }

    // --- Validation ---------------------------------------------------------

    private function validateFields(array $in, array &$errors, bool $creating): array
    {
        $name = $this->clean($in['author_name'] ?? '');
        $title = $this->clean($in['title'] ?? '');
        $description = is_string($in['description'] ?? null) ? trim(str_replace("\r\n", "\n", $in['description'])) : '';
        $email = $this->clean($in['author_email'] ?? '');
        $policy = is_string($in['edit_policy'] ?? null) ? $in['edit_policy'] : 'own';

        if ($name === '') {
            $errors['author_name'] = 'error.required';
        } elseif (mb_strlen($name) > 100) {
            $errors['author_name'] = 'error.too_long';
        }
        if ($title === '') {
            $errors['title'] = 'error.required';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'error.too_long';
        }
        if (mb_strlen($description) > 5000) {
            $errors['description'] = 'error.too_long';
        }
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['author_email'] = 'error.invalid_email';
        }
        if (!in_array($policy, self::POLICIES, true)) {
            $errors['edit_policy'] = 'error.invalid_value';
        }

        $deadline = null;
        $rawDeadline = $this->clean($in['deadline'] ?? '');
        if ($rawDeadline !== '') {
            $deadline = $this->parseDateTime($rawDeadline);
            if ($deadline === null) {
                $errors['deadline'] = 'error.invalid_date';
            } elseif ($creating && $deadline <= $this->nowString()) {
                $errors['deadline'] = 'error.deadline_past';
            }
        }

        return [
            'author_name' => $name,
            'author_email' => $email === '' ? null : $email,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'edit_policy' => $policy,
            'allow_maybe' => !empty($in['allow_maybe']) ? 1 : 0,
            'deadline' => $deadline,
        ];
    }

    /** @return list<array{date: null, label: string}> */
    private function parseClassicRows(mixed $rows, array &$errors): array
    {
        $out = [];
        $seen = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $label = $this->clean($row);
            if ($label === '') {
                continue;
            }
            if (mb_strlen($label) > 255) {
                $errors['options'] = 'error.too_long';
                continue;
            }
            $key = mb_strtolower($label);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = ['date' => null, 'label' => $label];
            }
        }
        if (count($out) > self::MAX_OPTIONS) {
            $errors['options'] = 'error.too_many_options';
        }
        return $out;
    }

    /**
     * Chaque ligne : ['date' => 'AAAA-MM-JJ', 'times' => '19h30, 21h00'] ; une option par horaire,
     * ou une seule option sans horaire.
     *
     * @return list<array{date: string, label: string}>
     */
    private function parseDateRows(mixed $rows, array &$errors): array
    {
        $out = [];
        $seen = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = $this->clean($row['date'] ?? '');
            $times = is_string($row['times'] ?? null) ? $row['times'] : '';
            if ($date === '') {
                if (trim($times) !== '') {
                    $errors['options'] = 'error.date_missing';
                }
                continue;
            }
            if (!$this->isValidDate($date)) {
                $errors['options'] = 'error.invalid_date';
                continue;
            }
            $labels = array_values(array_filter(
                array_map(fn ($t) => $this->clean($t), preg_split('/[,;\n]+/', $times) ?: []),
                static fn (string $t) => $t !== ''
            ));
            foreach ($labels ?: [''] as $label) {
                if (mb_strlen($label) > 50) {
                    $errors['options'] = 'error.too_long';
                    continue;
                }
                $key = $date . '|' . mb_strtolower($label);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $out[] = ['date' => $date, 'label' => $label];
                }
            }
        }
        usort($out, static fn (array $a, array $b) => strcmp($a['date'], $b['date']));
        if (count($out) > self::MAX_OPTIONS) {
            $errors['options'] = 'error.too_many_options';
        }
        return $out;
    }

    private function parseDateTime(string $raw): ?string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $d = DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($d !== false && $d->format($format) === $raw) {
                return $d->format('Y-m-d H:i:s');
            }
        }
        return $this->isValidDate($raw) ? $raw . ' 23:59:59' : null;
    }

    private function isValidDate(string $raw): bool
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        return $d !== false && $d->format('Y-m-d') === $raw && $d->format('Y') >= 2000 && $d->format('Y') <= 2100;
    }

    private function clean(mixed $value): string
    {
        return is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : '';
    }
}
