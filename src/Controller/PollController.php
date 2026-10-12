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

/** Création d'un sondage et participation publique. */
final class PollController extends Controller
{
    use RendersPoll;

    private const VOTER_COOKIE_PREFIX = 'd2v_';
    private const MAX_REMEMBERED_VOTES = 20;

    public function createForm(Request $r, array $p): Response
    {
        $type = $this->checkType($p['type']);
        return $this->render('create', [
            'title' => t($type === 'date' ? 'create.title_date' : 'create.title_classic'),
            'type' => $type,
            'errors' => $this->app->session->takeFlash('create_errors', []),
            'old' => $this->app->session->takeFlash('create_old'),
        ]);
    }

    public function create(Request $r, array $p): Response
    {
        $type = $this->checkType($p['type']);
        // Champ piège invisible pour les robots.
        if ($r->postString('website') !== '') {
            throw new HttpException(400, 'error.generic');
        }
        try {
            $created = $this->app->polls()->create($type, $r->post, $this->app->i18n->lang());
        } catch (ValidationException $e) {
            $this->app->session->flash('create_errors', $e->errors);
            $this->app->session->flash('create_old', $r->post);
            return $this->redirect('/create/' . $type);
        }

        $poll = $created['poll'];
        $mailStatus = 'none';
        try {
            if ($this->app->notifier()->pollCreated($poll, $created['admin_token'])) {
                $mailStatus = 'sent';
            }
        } catch (\Throwable $e) {
            $this->app->log('Email de création (' . $poll['public_id'] . ') : ' . $e->getMessage());
            $mailStatus = 'failed';
        }
        $this->app->session->flash('created', true);
        $this->app->session->flash('mail_status', $mailStatus);

        return $this->redirect('/a/' . $poll['public_id'] . '/' . $created['admin_token']);
    }

    public function show(Request $r, array $p): Response
    {
        $poll = $this->loadPoll($p['pid']);
        return $this->renderPoll($r, $poll, $this->visitorContext($r, $poll), '/p/' . $poll['public_id']);
    }

    public function vote(Request $r, array $p): Response
    {
        $poll = $this->loadPoll($p['pid']);
        $base = '/p/' . $poll['public_id'];
        [$response, $result] = $this->handleVote($r, $poll, $this->visitorContext($r, $poll), $base);
        if ($result !== null && $result['token'] !== null) {
            // Mémorise le jeton dans le navigateur pour pouvoir modifier ce vote plus tard.
            $this->rememberVote($r, $response, $poll, $result['vote_id'], $result['token']);
            if ($poll['edit_policy'] === 'own') {
                $this->app->session->flash('vote_link', $this->app->absoluteUrl(
                    $base . '/v/' . $result['vote_id'] . '/' . $result['token']
                ));
            }
        }
        return $response;
    }

    public function deleteVote(Request $r, array $p): Response
    {
        $poll = $this->loadPoll($p['pid']);
        return $this->handleDeleteVote($poll, (int) $p['vid'], $this->visitorContext($r, $poll), '/p/' . $poll['public_id']);
    }

    /** Lien personnel de modification : mémorise le jeton sur cet appareil puis ouvre l'édition. */
    public function claim(Request $r, array $p): Response
    {
        $poll = $this->loadPoll($p['pid']);
        $voteId = (int) $p['vid'];
        if (!Security::isToken($p['token']) || !$this->app->polls()->voteMatchesToken($poll, $voteId, $p['token'])) {
            throw new HttpException(404, 'error.vote_not_found');
        }
        $response = $this->redirect('/p/' . $poll['public_id'] . '?edit=' . $voteId . '#vote');
        $this->rememberVote($r, $response, $poll, $voteId, $p['token']);
        return $response;
    }

    private function checkType(string $type): string
    {
        if (!in_array($type, PollService::TYPES, true)) {
            throw new HttpException(404, 'error.not_found');
        }
        return $type;
    }

    private function visitorContext(Request $r, array $poll): AccessContext
    {
        return new AccessContext(false, $this->rememberedVotes($r, $poll));
    }

    /** @return array<int, string> */
    private function rememberedVotes(Request $r, array $poll): array
    {
        $raw = $r->cookie(self::VOTER_COOKIE_PREFIX . $poll['public_id']);
        $data = $raw !== null ? json_decode($raw, true) : null;
        $tokens = [];
        foreach (is_array($data) ? $data : [] as $id => $token) {
            if (is_numeric($id) && is_string($token) && Security::isToken($token)) {
                $tokens[(int) $id] = $token;
            }
        }
        return $tokens;
    }

    private function rememberVote(Request $r, Response $response, array $poll, int $voteId, string $token): void
    {
        $tokens = $this->rememberedVotes($r, $poll);
        $tokens[$voteId] = $token;
        $tokens = array_slice($tokens, -self::MAX_REMEMBERED_VOTES, null, true);
        $response->withCookie(
            self::VOTER_COOKIE_PREFIX . $poll['public_id'],
            json_encode($tokens, JSON_THROW_ON_ERROR),
            time() + 400 * 86400,
            $this->app->cookiePath(),
            $this->app->isSecure(),
        );
    }
}
