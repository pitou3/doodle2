<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Service\AccessContext;
use App\Service\ValidationException;

/** Affichage et vote, communs à la page publique et à la page de gestion. */
trait RendersPoll
{
    protected function renderPoll(Request $r, array $poll, AccessContext $ctx, string $base, array $extra = []): Response
    {
        $service = $this->app->polls();
        $repo = $this->app->pollRepo();
        $options = $repo->options((int) $poll['id']);
        $votes = $repo->votes((int) $poll['id']);
        $session = $this->app->session;

        $editable = [];
        foreach ($votes as $vote) {
            if ($service->canEditVote($poll, $vote, $ctx)) {
                $editable[(int) $vote['id']] = true;
            }
        }
        $editId = (int) $r->queryString('edit', '0');
        $editVote = null;
        foreach ($votes as $vote) {
            if ((int) $vote['id'] === $editId && isset($editable[$editId])) {
                $editVote = $vote;
            }
        }

        return $this->render('poll', [
            'title' => $poll['title'],
            'poll' => $poll,
            'options' => $options,
            'votes' => $votes,
            'results' => $service->results($options, $votes),
            'base' => $base,
            'manager' => $ctx->manager,
            'editable' => $editable,
            'editVote' => $editVote,
            'isOpen' => $service->isOpen($poll),
            'canAdd' => $service->canAddVote($poll, $ctx),
            'purgeDate' => $service->purgeDate($poll),
            'publicUrl' => $this->app->absoluteUrl('/p/' . $poll['public_id']),
            'voteErrors' => $session->takeFlash('vote_errors', []),
            'voteOld' => $session->takeFlash('vote_old'),
            'voteLink' => $session->takeFlash('vote_link'),
        ] + $extra);
    }

    /** @return array{0: Response, 1: ?array} réponse et résultat du vote (null si erreur) */
    protected function handleVote(Request $r, array $poll, AccessContext $ctx, string $base): array
    {
        try {
            $result = $this->app->polls()->submitVote($poll, $r->post, $ctx);
        } catch (ValidationException $e) {
            $this->app->session->flash('vote_errors', $e->errors);
            $this->app->session->flash('vote_old', $r->post);
            $voteId = (int) $r->postString('vote_id', '0');
            return [$this->redirect($base . ($voteId > 0 ? '?edit=' . $voteId : '') . '#vote'), null];
        }
        $this->message('success', 'flash.vote_saved');
        return [$this->redirect($base . '#results'), $result];
    }

    protected function handleDeleteVote(array $poll, int $voteId, AccessContext $ctx, string $base): Response
    {
        $this->app->polls()->deleteVote($poll, $voteId, $ctx);
        $this->message('success', 'flash.vote_deleted');
        return $this->redirect($base . '#results');
    }
}
