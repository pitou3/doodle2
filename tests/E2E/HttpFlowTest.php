<?php
declare(strict_types=1);

namespace Tests\E2E;

use App\Database;
use App\Migrator;
use App\Repository\AdminRepository;
use PHPUnit\Framework\TestCase;

/**
 * Parcours complets à travers le serveur web (Apache + PHP + MariaDB + Mailpit).
 * Lancé dans le conteneur : E2E_BASE_URL=http://localhost vendor/bin/phpunit --testsuite e2e
 */
final class HttpFlowTest extends TestCase
{
    private static string $base;

    public static function setUpBeforeClass(): void
    {
        $base = getenv('E2E_BASE_URL');
        if (!$base) {
            self::markTestSkipped('E2E_BASE_URL non défini');
        }
        self::$base = rtrim($base, '/');
        // L'application de dev utilise la base « doodle2 » : on s'assure qu'elle est à jour.
        $pdo = Database::connect([
            'host' => getenv('DB_HOST') ?: 'db', 'port' => 3306, 'name' => getenv('DB_NAME') ?: 'doodle2',
            'user' => getenv('DB_USER') ?: 'doodle2', 'password' => getenv('DB_PASSWORD') ?: 'doodle2',
        ]);
        (new Migrator($pdo, dirname(__DIR__, 2) . '/migrations'))->migrate();
        $admins = new AdminRepository($pdo);
        $existing = $admins->findByUsername('e2e-admin');
        $hash = password_hash('e2e-password-123', PASSWORD_DEFAULT);
        $existing ? $admins->updatePassword((int) $existing['id'], $hash) : $admins->create('e2e-admin', $hash, date('Y-m-d H:i:s'));
        $pdo->exec("DELETE FROM login_attempts");
    }

    private function client(string $lang = 'fr'): HttpClient
    {
        return new HttpClient(self::$base, $lang);
    }

    public function testHomeLanguageDetectionAndSwitch(): void
    {
        $fr = $this->client('fr')->get('/');
        self::assertSame(200, $fr->status);
        self::assertStringContainsString('Sondage de dates', $fr->body);
        self::assertStringContainsString('Sondage classique', $fr->body);
        self::assertSame('no-referrer', $fr->headers['referrer-policy']);

        $en = $this->client('en')->get('/');
        self::assertStringContainsString('Date poll', $en->body);
        self::assertStringContainsString('<html lang="en">', $en->body);

        // Choix explicite mémorisé dans un cookie, prioritaire sur le navigateur.
        $switch = $this->client('en');
        $switch->get('/lang/fr?r=' . rawurlencode('/create/date'));
        self::assertSame(303, $switch->status);
        self::assertSame('/create/date', $switch->location());
        self::assertStringContainsString('Nouveau sondage de dates', $switch->follow()->body);

        // Pas de redirection ouverte vers un autre site.
        $switch->get('/lang/en?r=' . rawurlencode('//evil.example'));
        self::assertSame('/', $switch->location());
    }

    public function testFullDatePollFlow(): void
    {
        $email = 'creator-' . bin2hex(random_bytes(4)) . '@example.com';
        $creator = $this->client();
        $creator->get('/create/date');
        self::assertSame(200, $creator->status);

        // Champs obligatoires manquants : retour au formulaire avec erreurs.
        $creator->post('/create/date', ['author_name' => '', 'title' => '', 'dates' => [['date' => '2030-01-10', 'times' => '']]]);
        self::assertSame(303, $creator->status);
        $creator->follow();
        self::assertStringContainsString('Ce champ est obligatoire', $creator->body);

        $creator->post('/create/date', [
            'author_name' => 'Camille',
            'author_email' => $email,
            'title' => 'Stage Balboa <script>',
            'description' => 'Choisissez vos dates',
            'edit_policy' => 'own',
            'allow_maybe' => '1',
            'deadline' => '',
            'dates' => [
                ['date' => '2030-01-10', 'times' => '19h, 21h'],
                ['date' => '2030-01-11', 'times' => ''],
            ],
        ]);
        self::assertSame(303, $creator->status);
        self::assertMatchesRegularExpression('#/a/([a-z0-9]{16})/([a-z0-9]{32})$#', $creator->location());
        preg_match('#/a/([a-z0-9]{16})/([a-z0-9]{32})$#', $creator->location(), $m);
        [, $pid, $token] = $m;
        $adminPath = '/a/' . $pid . '/' . $token;

        $creator->follow();
        self::assertSame(200, $creator->status);
        self::assertStringContainsString('Votre sondage est créé', $creator->body);
        self::assertStringContainsString('Stage Balboa &lt;script&gt;', $creator->body, 'titre échappé');
        self::assertStringContainsString($adminPath, $creator->body);

        // Email au créateur, capturé par Mailpit.
        $this->assertMailContains($email, $adminPath);

        // Mauvais jeton d'administration.
        self::assertSame(404, $this->client()->get('/a/' . $pid . '/' . str_repeat('a', 32))->status);

        // Premier participant.
        $alice = $this->client();
        $alice->get('/p/' . $pid);
        self::assertSame(200, $alice->status);
        preg_match_all('/name="choices\[(\d+)\]" value="2"/', $alice->body, $ids);
        self::assertCount(3, $ids[1], '3 options (2 horaires + 1 date)');
        [$o1, $o2, $o3] = $ids[1];
        $alice->post('/p/' . $pid . '/vote', ['name' => 'Alice', 'choices' => [$o1 => '2', $o2 => '1', $o3 => '0']]);
        self::assertSame(303, $alice->status);
        self::assertArrayHasKey('d2v_' . $pid, $alice->cookies());
        $alice->follow();
        self::assertStringContainsString('Votre lien personnel', $alice->body);
        preg_match('#/p/' . $pid . '/v/(\d+)/([a-z0-9]{24})#', $alice->body, $link);
        self::assertNotEmpty($link, 'lien personnel affiché');
        $aliceVote = (int) $link[1];

        // Nom déjà utilisé.
        $bob = $this->client();
        $bob->get('/p/' . $pid);
        $bob->post('/p/' . $pid . '/vote', ['name' => 'alice', 'choices' => [$o1 => '2']]);
        $bob->follow();
        self::assertStringContainsString('Ce nom a déjà été utilisé', $bob->body);

        // Bob ne peut pas modifier le vote d'Alice (règle « chacun son vote »).
        $bob->post('/p/' . $pid . '/vote', ['vote_id' => (string) $aliceVote, 'name' => 'Pirate']);
        self::assertSame(403, $bob->status);
        $bob->get('/p/' . $pid);
        $bob->post('/p/' . $pid . '/vote/' . $aliceVote . '/delete', []);
        self::assertSame(403, $bob->status);

        // Alice modifie son vote.
        $alice->get('/p/' . $pid . '?edit=' . $aliceVote);
        self::assertStringContainsString('Modifier le vote', $alice->body);
        $alice->post('/p/' . $pid . '/vote', ['vote_id' => (string) $aliceVote, 'name' => 'Alice M.', 'choices' => [$o3 => '2']]);
        self::assertSame(303, $alice->status);
        self::assertStringContainsString('Alice M.', $alice->follow()->body);

        // Le lien personnel fonctionne depuis un autre appareil.
        $other = $this->client();
        $other->get('/p/' . $pid . '/v/' . $aliceVote . '/' . $link[2]);
        self::assertSame(303, $other->status);
        self::assertStringContainsString('Modifier le vote', $other->follow()->body);

        // CSRF obligatoire.
        self::assertSame(400, $this->client()->post('/p/' . $pid . '/vote', ['name' => 'X'], false)->status);

        // Le créateur clôture le sondage : plus de vote possible pour les participants.
        $bobCsrf = $bob->get('/p/' . $pid)->csrf(); // la page clôturée n'aura plus de formulaire
        $creator->get($adminPath);
        $creator->post($adminPath . '/settings', [
            'author_name' => 'Camille', 'author_email' => $email, 'title' => 'Stage Balboa',
            'description' => '', 'edit_policy' => 'own', 'allow_maybe' => '1', 'deadline' => '', 'closed' => '1',
        ]);
        self::assertSame(303, $creator->status);
        $bob->get('/p/' . $pid);
        self::assertStringContainsString('clôturé', $bob->body);
        self::assertStringNotContainsString('name="choices[', $bob->body);
        $bob->post('/p/' . $pid . '/vote', ['_csrf' => $bobCsrf, 'name' => 'Bob', 'choices' => [$o1 => '2']]);
        self::assertSame(403, $bob->status);

        // …mais le créateur peut encore modifier les votes.
        $creator->get($adminPath);
        $creator->post($adminPath . '/vote', ['vote_id' => (string) $aliceVote, 'name' => 'Alice', 'choices' => [$o1 => '2']]);
        self::assertSame(303, $creator->status);

        // Export CSV.
        $csv = $creator->get($adminPath . '/export.csv');
        self::assertSame(200, $csv->status);
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv->body);
        self::assertStringContainsString('Alice;Oui;Non;Non', $csv->body);

        // Suppression par le créateur.
        $creator->get($adminPath);
        $creator->post($adminPath . '/delete', []);
        self::assertSame(303, $creator->status);
        self::assertSame(404, $this->client()->get('/p/' . $pid)->status);
    }

    public function testAllAndNonePolicies(): void
    {
        foreach (['all' => true, 'none' => false] as $policy => $othersCanEdit) {
            $creator = $this->client();
            $creator->get('/create/classic');
            $creator->post('/create/classic', [
                'author_name' => 'Dom', 'title' => 'Politique ' . $policy, 'edit_policy' => $policy,
                'options' => ['Lieu A', 'Lieu B'],
            ]);
            preg_match('#/a/([a-z0-9]{16})/#', $creator->location(), $m);
            $pid = $m[1];

            $voter = $this->client();
            $voter->get('/p/' . $pid);
            self::assertStringNotContainsString('Peut-être', $voter->body, '« peut-être » non coché à la création');
            $voter->post('/p/' . $pid . '/vote', ['name' => 'Eve']);
            $voter->follow();
            preg_match('#/p/' . $pid . '\?edit=(\d+)#', $voter->body, $edit);

            $stranger = $this->client();
            $stranger->get('/p/' . $pid);
            $hasEditLink = (bool) preg_match('#\?edit=\d+#', $stranger->body);
            self::assertSame($othersCanEdit, $hasEditLink, "politique $policy : lien de modification pour un tiers");

            if ($policy === 'none') {
                self::assertEmpty($edit, 'politique none : même l\'auteur du vote ne peut pas modifier');
            }
        }
    }

    public function testAdminModeration(): void
    {
        $creator = $this->client();
        $creator->get('/create/classic');
        $creator->post('/create/classic', ['author_name' => 'Fred', 'title' => 'À modérer', 'options' => ['Oui', 'Non']]);
        preg_match('#/a/([a-z0-9]{16})/#', $creator->location(), $m);
        $pid = $m[1];

        $admin = $this->client();
        $admin->get('/admin');
        self::assertSame(303, $admin->status);
        self::assertStringEndsWith('/admin/login', $admin->location());

        $admin->get('/admin/login');
        $admin->post('/admin/login', ['username' => 'e2e-admin', 'password' => 'mauvais']);
        self::assertStringContainsString('incorrect', $admin->follow()->body);

        $admin->post('/admin/login', ['username' => 'e2e-admin', 'password' => 'e2e-password-123']);
        self::assertSame(303, $admin->status);
        $admin->follow();
        self::assertSame(200, $admin->status);
        self::assertStringContainsString('À modérer', $admin->body);

        $admin->get('/admin?q=' . rawurlencode('modérer'));
        self::assertStringContainsString('/admin/poll/' . $pid, $admin->body);

        $admin->get('/admin/poll/' . $pid);
        self::assertSame(200, $admin->status);
        self::assertStringContainsString('Modération', $admin->body);
        self::assertStringNotContainsString('/a/' . $pid . '/', $admin->body, 'le lien secret du créateur n\'est pas exposé');

        $admin->post('/admin/poll/' . $pid . '/delete', []);
        self::assertSame(303, $admin->status);
        self::assertSame(404, $this->client()->get('/p/' . $pid)->status);

        // Une session non connectée ne peut pas modérer.
        self::assertSame(303, $this->client()->get('/admin/poll/' . $pid)->status);
    }

    public function testCronEndpointRequiresKey(): void
    {
        self::assertSame(404, $this->client()->get('/cron?key=faux')->status);
        $ok = $this->client()->get('/cron?key=dev-cron-key');
        self::assertSame(200, $ok->status);
        self::assertStringStartsWith('OK purged=', $ok->body);
    }

    private function assertMailContains(string $to, string $needle): void
    {
        $api = getenv('MAILPIT_URL') ?: 'http://mailpit:8025';
        $search = @file_get_contents($api . '/api/v1/search?query=' . rawurlencode('to:' . $to));
        if ($search === false) {
            self::markTestIncomplete('Mailpit injoignable');
        }
        $messages = json_decode($search, true)['messages'] ?? [];
        self::assertNotEmpty($messages, "email envoyé à $to");
        $message = json_decode((string) file_get_contents($api . '/api/v1/message/' . $messages[0]['ID']), true);
        self::assertStringContainsString($needle, $message['Text']);
        self::assertStringContainsString('Stage Balboa', $message['Subject']);
    }
}
