<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Http\HttpException;
use App\Repository\PollRepository;
use App\Service\AccessContext;
use App\Service\PollService;
use App\Service\ValidationException;
use DateTimeImmutable;

final class PollServiceTest extends DbTestCase
{
    private PollRepository $repo;
    private PollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PollRepository(self::pdo());
        $this->service = new PollService($this->repo, 6, new DateTimeImmutable('2026-10-12 10:00:00'));
    }

    private function base(array $overrides = []): array
    {
        return $overrides + [
            'author_name' => 'Alice',
            'author_email' => '',
            'title' => 'Soirée lindy',
            'description' => '',
            'edit_policy' => 'own',
            'allow_maybe' => '1',
            'deadline' => '',
        ];
    }

    private function classic(array $overrides = []): array
    {
        return $this->service->create('classic', $this->base($overrides) + ['options' => ['Pizza', 'Sushi', '']], 'fr');
    }

    public function testNameAndTitleAreMandatory(): void
    {
        try {
            $this->service->create('classic', $this->base(['author_name' => '  ', 'title' => '']) + ['options' => ['A']], 'fr');
            self::fail('ValidationException attendue');
        } catch (ValidationException $e) {
            self::assertSame('error.required', $e->errors['author_name']);
            self::assertSame('error.required', $e->errors['title']);
        }
    }

    public function testAtLeastOneOptionRequired(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create('classic', $this->base() + ['options' => ['', ' ']], 'fr');
    }

    public function testCreateClassicPoll(): void
    {
        $created = $this->classic(['description' => "Ligne 1\nLigne 2", 'author_email' => 'alice@example.com']);
        $poll = $created['poll'];
        self::assertSame('Soirée lindy', $poll['title']);
        self::assertSame("Ligne 1\nLigne 2", $poll['description']);
        self::assertSame('alice@example.com', $poll['author_email']);
        self::assertMatchesRegularExpression('/^[a-z0-9]{16}$/', $poll['public_id']);
        self::assertSame(32, strlen($created['admin_token']));
        self::assertSame(hash('sha256', $created['admin_token']), $poll['admin_token_hash']);
        self::assertNull($poll['ends_at'], 'classique sans date limite : pas de fin');
        self::assertSame(['Pizza', 'Sushi'], array_column($this->repo->options((int) $poll['id']), 'label'));
    }

    public function testCreateDatePollSplitsTimesAndSortsDates(): void
    {
        $poll = $this->service->create('date', $this->base() + ['dates' => [
            ['date' => '2026-11-20', 'times' => '19h30, 21h'],
            ['date' => '2026-11-05', 'times' => ''],
            ['date' => '', 'times' => ''],
        ]], 'fr')['poll'];

        $options = $this->repo->options((int) $poll['id']);
        self::assertSame(['2026-11-05', '2026-11-20', '2026-11-20'], array_column($options, 'option_date'));
        self::assertSame(['', '19h30', '21h'], array_column($options, 'label'));
        // Sans date limite, le sondage de dates se termine à la fin de la dernière date.
        self::assertSame('2026-11-20 23:59:59', $poll['ends_at']);
    }

    public function testInvalidDateIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create('date', $this->base() + ['dates' => [['date' => '2026-02-30', 'times' => '']]], 'fr');
    }

    public function testDeadlineMustBeInFutureAndDefinesEnd(): void
    {
        try {
            $this->classic(['deadline' => '2026-10-01T12:00']);
            self::fail('date limite passée acceptée');
        } catch (ValidationException $e) {
            self::assertSame('error.deadline_past', $e->errors['deadline']);
        }
        $poll = $this->classic(['deadline' => '2026-10-20T18:30'])['poll'];
        self::assertSame('2026-10-20 18:30:00', $poll['deadline']);
        self::assertSame('2026-10-20 18:30:00', $poll['ends_at']);
    }

    public function testVoteAndOwnPolicy(): void
    {
        $poll = $this->classic()['poll'];
        [$pizza, $sushi] = array_map('intval', array_column($this->repo->options((int) $poll['id']), 'id'));

        $res = $this->service->submitVote($poll, ['name' => 'Bob', 'choices' => [$pizza => '2', $sushi => '1']], new AccessContext());
        self::assertNotNull($res['token']);
        $votes = $this->repo->votes((int) $poll['id']);
        self::assertSame([$pizza => 2, $sushi => 1], $votes[0]['choices']);

        $vote = $this->repo->findVote($res['vote_id']);
        $owner = new AccessContext(false, [$res['vote_id'] => $res['token']]);
        $stranger = new AccessContext(false, [$res['vote_id'] => 'mauvaisjeton12345']);
        self::assertTrue($this->service->canEditVote($poll, $vote, $owner));
        self::assertFalse($this->service->canEditVote($poll, $vote, $stranger));
        self::assertFalse($this->service->canEditVote($poll, $vote, new AccessContext()));
        self::assertTrue($this->service->canEditVote($poll, $vote, new AccessContext(true)));

        $this->service->submitVote($poll, ['vote_id' => $res['vote_id'], 'name' => 'Bobby', 'choices' => [$pizza => '0']], $owner);
        self::assertSame('Bobby', $this->repo->findVote($res['vote_id'])['name']);

        $this->expectException(HttpException::class);
        $this->service->submitVote($poll, ['vote_id' => $res['vote_id'], 'name' => 'Pirate'], $stranger);
    }

    public function testAllPolicyLetsAnyoneEdit(): void
    {
        $poll = $this->classic(['edit_policy' => 'all'])['poll'];
        $res = $this->service->submitVote($poll, ['name' => 'Bob'], new AccessContext());
        self::assertTrue($this->service->canEditVote($poll, $this->repo->findVote($res['vote_id']), new AccessContext()));
    }

    public function testNonePolicyLocksVotesExceptForManagers(): void
    {
        $poll = $this->classic(['edit_policy' => 'none'])['poll'];
        $res = $this->service->submitVote($poll, ['name' => 'Bob'], new AccessContext());
        $vote = $this->repo->findVote($res['vote_id']);
        $owner = new AccessContext(false, [$res['vote_id'] => $res['token']]);
        self::assertFalse($this->service->canEditVote($poll, $vote, $owner));
        self::assertTrue($this->service->canEditVote($poll, $vote, new AccessContext(true)));

        $this->service->deleteVote($poll, $res['vote_id'], new AccessContext(true));
        self::assertNull($this->repo->findVote($res['vote_id']));
    }

    public function testMaybeRefusedWhenDisabled(): void
    {
        $poll = $this->classic(['allow_maybe' => ''])['poll'];
        $optionId = (int) $this->repo->options((int) $poll['id'])[0]['id'];
        try {
            $this->service->submitVote($poll, ['name' => 'Bob', 'choices' => [$optionId => '1']], new AccessContext());
            self::fail('« peut-être » accepté alors que désactivé');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('choices', $e->errors);
        }
    }

    public function testDuplicateNamesRefused(): void
    {
        $poll = $this->classic()['poll'];
        $this->service->submitVote($poll, ['name' => 'Chloé'], new AccessContext());
        try {
            $this->service->submitVote($poll, ['name' => 'chloe'], new AccessContext());
            self::fail('nom en double accepté');
        } catch (ValidationException $e) {
            self::assertSame('error.name_taken', $e->errors['name']);
        }
    }

    public function testDeadlineClosesPollForVotersButNotManagers(): void
    {
        $poll = $this->classic(['deadline' => '2026-10-15T20:00'])['poll'];
        $res = $this->service->submitVote($poll, ['name' => 'Bob'], new AccessContext());
        $owner = new AccessContext(false, [$res['vote_id'] => $res['token']]);

        $this->service->setNow(new DateTimeImmutable('2026-10-16 08:00:00'));
        self::assertFalse($this->service->isOpen($poll));
        self::assertFalse($this->service->canEditVote($poll, $this->repo->findVote($res['vote_id']), $owner));
        self::assertTrue($this->service->canAddVote($poll, new AccessContext(true)));

        $this->expectException(HttpException::class);
        $this->service->submitVote($poll, ['name' => 'Retardataire'], new AccessContext());
    }

    public function testManualCloseAndReopen(): void
    {
        $poll = $this->classic()['poll'];
        $settings = $this->base(['closed' => '1']);
        $this->service->updateSettings($poll, $settings);
        $poll = $this->repo->findById((int) $poll['id']);
        self::assertSame('2026-10-12 10:00:00', $poll['closed_at']);
        self::assertSame('2026-10-12 10:00:00', $poll['ends_at']);

        $this->service->setNow(new DateTimeImmutable('2026-10-12 11:00:00'));
        self::assertFalse($this->service->isOpen($poll));

        $this->service->updateSettings($poll, $this->base());
        $poll = $this->repo->findById((int) $poll['id']);
        self::assertNull($poll['closed_at']);
        self::assertTrue($this->service->isOpen($poll));
    }

    public function testUpdateOptions(): void
    {
        $poll = $this->service->create('date', $this->base() + ['dates' => [
            ['date' => '2026-11-05', 'times' => ''],
            ['date' => '2026-11-06', 'times' => ''],
        ]], 'fr')['poll'];
        [$first, $second] = array_map('intval', array_column($this->repo->options((int) $poll['id']), 'id'));
        $vote = $this->service->submitVote($poll, ['name' => 'Bob', 'choices' => [$first => '2', $second => '2']], new AccessContext());

        $this->service->updateOptions($poll, [
            'existing' => [$first => ['date' => '2026-11-05', 'label' => '20h'], $second => ['delete' => '1']],
            'dates' => [['date' => '2026-12-01', 'times' => '']],
        ]);
        $options = $this->repo->options((int) $poll['id']);
        self::assertSame(['2026-11-05', '2026-12-01'], array_column($options, 'option_date'));
        self::assertSame('20h', $options[0]['label']);
        self::assertSame([$first => 2], $this->repo->votes((int) $poll['id'])[0]['choices']);
        self::assertSame('2026-12-01 23:59:59', $this->repo->findById((int) $poll['id'])['ends_at']);

        $this->expectException(ValidationException::class);
        $this->service->updateOptions($poll, ['existing' => [
            $first => ['delete' => '1'],
            (int) $options[1]['id'] => ['delete' => '1'],
        ]]);
    }

    public function testResultsAndBestOption(): void
    {
        $poll = $this->classic()['poll'];
        [$pizza, $sushi] = array_map('intval', array_column($options = $this->repo->options((int) $poll['id']), 'id'));
        $ctx = new AccessContext();
        $this->service->submitVote($poll, ['name' => 'A', 'choices' => [$pizza => '2', $sushi => '2']], $ctx);
        $this->service->submitVote($poll, ['name' => 'B', 'choices' => [$pizza => '2', $sushi => '1']], $ctx);
        $this->service->submitVote($poll, ['name' => 'C', 'choices' => [$pizza => '0', $sushi => '2']], $ctx);

        $results = $this->service->results($options, $this->repo->votes((int) $poll['id']));
        self::assertSame(2, $results[$pizza]['yes']);
        self::assertSame(2, $results[$sushi]['yes']);
        self::assertSame(1, $results[$sushi]['maybe']);
        self::assertTrue($results[$sushi]['best']);
        self::assertFalse($results[$pizza]['best']);
        self::assertSame(['B'], $results[$sushi]['maybe_names']);
    }

    public function testPurgeAfterRetention(): void
    {
        $closed = $this->classic(['deadline' => '2026-10-13T00:00'])['poll'];
        $open = $this->classic(['title' => 'Sans fin'])['poll'];
        $datePoll = $this->service->create('date', $this->base() + ['dates' => [['date' => '2026-12-24', 'times' => '']]], 'fr')['poll'];

        self::assertSame('2027-04-13', $this->service->purgeDate($closed)->format('Y-m-d'));

        $this->service->setNow(new DateTimeImmutable('2027-04-12 09:00:00'));
        self::assertSame(0, $this->service->purgeExpired());

        // Le sondage clôturé le 13.10 est supprimé 6 mois plus tard ; le classique sans fin
        // (inactif depuis le 12.10) aussi ; le sondage de dates (fin le 24.12) est conservé.
        $this->service->setNow(new DateTimeImmutable('2027-04-14 00:00:00'));
        self::assertSame(2, $this->service->purgeExpired());
        self::assertNull($this->repo->findById((int) $closed['id']));
        self::assertNull($this->repo->findById((int) $open['id']));
        self::assertNotNull($this->repo->findById((int) $datePoll['id']));
    }

    public function testVoteFromAnotherPollIsRejected(): void
    {
        $a = $this->classic()['poll'];
        $b = $this->classic(['title' => 'Autre'])['poll'];
        $res = $this->service->submitVote($a, ['name' => 'Bob'], new AccessContext());

        $this->expectException(HttpException::class);
        $this->service->deleteVote($b, $res['vote_id'], new AccessContext(true));
    }
}
