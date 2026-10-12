<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Security;
use App\Service\AccessContext;
use App\Service\PollService;
use App\Service\ValidationException;

/**
 * Gestion d'un sondage, accessible :
 *  - au créateur via /a/{pid}/{token} (lien secret reçu à la création) ;
 *  - aux administrateurs connectés via /admin/poll/{pid}.
 */
final class ManageController extends Controller
{
    use RendersPoll;

    public function show(Request $r, array $p): Response
    {
        [$poll, $base, $isCreator] = $this->resolve($p);
        $session = $this->app->session;
        return $this->renderPoll($r, $poll, new AccessContext(true), $base, [
            'adminUrl' => $isCreator ? $this->app->absoluteUrl($base) : null,
            'isSiteAdmin' => !$isCreator,
            'created' => $session->takeFlash('created', false),
            'mailStatus' => $session->takeFlash('mail_status'),
            'settingsErrors' => $session->takeFlash('settings_errors', []),
            'settingsOld' => $session->takeFlash('settings_old'),
            'optionsErrors' => $session->takeFlash('options_errors', []),
            'optionsOld' => $session->takeFlash('options_old'),
        ]);
    }

    public function vote(Request $r, array $p): Response
    {
        [$poll, $base] = $this->resolve($p);
        return $this->handleVote($r, $poll, new AccessContext(true), $base)[0];
    }

    public function deleteVote(Request $r, array $p): Response
    {
        [$poll, $base] = $this->resolve($p);
        return $this->handleDeleteVote($poll, (int) $p['vid'], new AccessContext(true), $base);
    }

    public function settings(Request $r, array $p): Response
    {
        [$poll, $base] = $this->resolve($p);
        try {
            $this->app->polls()->updateSettings($poll, $r->post);
        } catch (ValidationException $e) {
            $this->app->session->flash('settings_errors', $e->errors);
            $this->app->session->flash('settings_old', $r->post);
            return $this->redirect($base . '#settings');
        }
        $this->message('success', 'flash.settings_saved');
        return $this->redirect($base);
    }

    public function options(Request $r, array $p): Response
    {
        [$poll, $base] = $this->resolve($p);
        try {
            $this->app->polls()->updateOptions($poll, $r->post);
        } catch (ValidationException $e) {
            $this->app->session->flash('options_errors', $e->errors);
            $this->app->session->flash('options_old', $r->post);
            return $this->redirect($base . '#options');
        }
        $this->message('success', 'flash.options_saved');
        return $this->redirect($base);
    }

    public function delete(Request $r, array $p): Response
    {
        [$poll, , $isCreator] = $this->resolve($p);
        $this->app->polls()->delete($poll);
        $this->message('success', 'flash.poll_deleted', ['title' => $poll['title']]);
        return $this->redirect($isCreator ? '/' : '/admin');
    }

    public function export(Request $r, array $p): Response
    {
        [$poll] = $this->resolve($p);
        $repo = $this->app->pollRepo();
        $options = $repo->options((int) $poll['id']);
        $votes = $repo->votes((int) $poll['id']);
        $results = $this->app->polls()->results($options, $votes);
        $i18n = $this->app->i18n;

        $labels = [];
        foreach ($options as $o) {
            $labels[] = $poll['type'] === 'date'
                ? trim($i18n->formatDay($o['option_date'], true) . ' ' . $o['label'])
                : $o['label'];
        }
        $values = [PollService::YES => t('vote.yes'), PollService::MAYBE => t('vote.maybe'), PollService::NO => t('vote.no')];

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
        $put = static function (array $row) use ($out): void {
            // Neutralise les formules (injection CSV).
            $row = array_map(static fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $row);
            fputcsv($out, $row, ';', '"', '');
        };
        $put([t('csv.participant'), ...$labels]);
        foreach ($votes as $vote) {
            $row = [$vote['name']];
            foreach ($options as $o) {
                $row[] = $values[$vote['choices'][(int) $o['id']] ?? PollService::NO];
            }
            $put($row);
        }
        $put([t('csv.total_yes'), ...array_map(static fn ($o) => $results[(int) $o['id']]['yes'], $options)]);
        if ($poll['allow_maybe']) {
            $put([t('csv.total_maybe'), ...array_map(static fn ($o) => $results[(int) $o['id']]['maybe'], $options)]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return (new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sondage-' . $poll['public_id'] . '.csv"',
        ]));
    }

    /** @return array{0: array, 1: string, 2: bool} sondage, URL de base, accès créateur */
    private function resolve(array $p): array
    {
        if (isset($p['token'])) {
            $poll = $this->app->pollRepo()->findByPublicId($p['pid']);
            if ($poll === null || !Security::isToken($p['token'])
                || !hash_equals((string) $poll['admin_token_hash'], Security::hash($p['token']))) {
                throw new HttpException(404, 'error.poll_not_found');
            }
            return [$poll, '/a/' . $poll['public_id'] . '/' . $p['token'], true];
        }
        $this->requireAdmin();
        $poll = $this->loadPoll($p['pid']);
        return [$poll, '/admin/poll/' . $poll['public_id'], false];
    }
}
