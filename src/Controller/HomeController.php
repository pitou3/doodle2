<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\I18n;

final class HomeController extends Controller
{
    public function index(Request $r, array $p): Response
    {
        return $this->render('home', ['title' => t('home.title')]);
    }

    public function lang(Request $r, array $p): Response
    {
        $lang = in_array($p['lang'], I18n::SUPPORTED, true) ? $p['lang'] : (string) $this->app->config['app']['default_lang'];
        $to = $r->queryString('r');
        // Uniquement une redirection interne (pas de « //domaine » ni d'URL absolue).
        if (!str_starts_with($to, '/') || str_starts_with($to, '//') || str_contains($to, '\\')) {
            $to = $this->app->url('/');
        }
        return Response::redirect($to)
            ->withCookie('lang', $lang, time() + 365 * 86400, $this->app->cookiePath(), $this->app->isSecure());
    }

    /** Point d'entrée pour une tâche planifiée Infomaniak : /cron?key=… */
    public function cron(Request $r, array $p): Response
    {
        $key = (string) $this->app->config['app']['cron_key'];
        if ($key === '' || !hash_equals($key, $r->queryString('key'))) {
            throw new HttpException(404, 'error.not_found');
        }
        $count = $this->app->runMaintenance();
        return Response::text('OK purged=' . $count . "\n");
    }
}
