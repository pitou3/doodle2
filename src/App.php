<?php
declare(strict_types=1);

namespace App;

use App\Controller\AdminController;
use App\Controller\HomeController;
use App\Controller\ManageController;
use App\Controller\PollController;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Mail\MailerFactory;
use App\Mail\Notifier;
use App\Repository\AdminRepository;
use App\Repository\PollRepository;
use App\Service\PollService;
use PDO;

final class App
{
    private static ?self $instance = null;

    public readonly I18n $i18n;
    public readonly Session $session;
    public readonly View $view;
    public readonly string $root;
    public Request $request;

    private string $basePath;
    private ?PDO $pdo = null;
    private ?PollService $polls = null;
    private ?array $currentAdmin = null;
    private bool $adminLoaded = false;

    public function __construct(public readonly array $config, ?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__);
        date_default_timezone_set((string) $config['app']['timezone']);
        $this->basePath = rtrim((string) parse_url((string) $config['app']['base_url'], PHP_URL_PATH), '/');
        $this->i18n = new I18n($this->root . '/lang', (string) $config['app']['default_lang']);
        $this->session = new Session();
        $this->view = new View($this->root . '/templates');
        self::$instance = $this;
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            throw new \LogicException('Application non initialisée');
        }
        return self::$instance;
    }

    // --- Services -----------------------------------------------------------

    public function db(): PDO
    {
        return $this->pdo ??= Database::connect($this->config['db']);
    }

    public function pollRepo(): PollRepository
    {
        return new PollRepository($this->db());
    }

    public function admins(): AdminRepository
    {
        return new AdminRepository($this->db());
    }

    public function polls(): PollService
    {
        return $this->polls ??= new PollService($this->pollRepo(), (int) $this->config['app']['retention_months']);
    }

    public function notifier(): Notifier
    {
        return new Notifier(
            MailerFactory::create($this->config['mail'], $this->logDir()),
            $this->i18n,
            (string) $this->config['app']['name'],
            rtrim((string) $this->config['app']['base_url'], '/'),
        );
    }

    public function logDir(): string
    {
        return $this->root . '/var/log';
    }

    public function log(string $message): void
    {
        @file_put_contents($this->logDir() . '/app.log', '[' . date('c') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
    }

    // --- URLs ---------------------------------------------------------------

    public function url(string $path = '/'): string
    {
        return $this->basePath . $path;
    }

    public function absoluteUrl(string $path): string
    {
        return rtrim((string) $this->config['app']['base_url'], '/') . $path;
    }

    public function cookiePath(): string
    {
        return $this->basePath === '' ? '/' : $this->basePath . '/';
    }

    public function isSecure(): bool
    {
        return str_starts_with((string) $this->config['app']['base_url'], 'https://')
            || (($this->request->server['HTTPS'] ?? '') !== '' && ($this->request->server['HTTPS'] ?? '') !== 'off');
    }

    // --- Administration -----------------------------------------------------

    /** Administrateur connecté, ou null. La session expire après 4 h d'inactivité. */
    public function currentAdmin(): ?array
    {
        if ($this->adminLoaded) {
            return $this->currentAdmin;
        }
        $this->adminLoaded = true;
        $id = (int) $this->session->get('admin_id', 0);
        if ($id <= 0) {
            return null;
        }
        if (time() - (int) $this->session->get('admin_seen', 0) > 4 * 3600) {
            $this->session->remove('admin_id');
            return null;
        }
        $this->session->set('admin_seen', time());
        return $this->currentAdmin = $this->admins()->findById($id);
    }

    public function forgetCurrentAdmin(): void
    {
        $this->adminLoaded = false;
        $this->currentAdmin = null;
    }

    // --- Traitement des requêtes -------------------------------------------

    public function handle(Request $request): Response
    {
        $this->request = $request;
        try {
            $this->session->start($this->isSecure(), $this->cookiePath());
            $this->i18n->setLang(I18n::detect(
                $request->cookie('lang'),
                $request->header('Accept-Language'),
                (string) $this->config['app']['default_lang'],
            ));
            $response = $this->dispatch($request);
        } catch (HttpException $e) {
            $response = $e->location !== null
                ? Response::redirect($e->location, $e->status)
                : $this->errorPage($e->status, $e->getMessage() ?: 'error.generic');
        } catch (\Throwable $e) {
            $this->log(get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $detail = $this->config['app']['debug'] ? get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() : null;
            $response = $this->errorPage(500, 'error.server', $detail);
        }
        return $this->withSecurityHeaders($response);
    }

    private function dispatch(Request $request): Response
    {
        $path = $request->path;
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        if ($path === '' || $path === false) {
            $path = '/';
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            return Response::redirect($this->url(rtrim($path, '/')), 301);
        }

        if ($request->method === 'POST' && !$this->session->checkCsrf($request->post('_csrf'))) {
            throw new HttpException(400, 'error.csrf');
        }

        $this->maybeAutoPurge();

        return $this->router()->dispatch($request, $path) ?? throw new HttpException(404, 'error.not_found');
    }

    private function router(): Router
    {
        $r = new Router();
        $home = new HomeController($this);
        $poll = new PollController($this);
        $manage = new ManageController($this);
        $admin = new AdminController($this);

        $r->get('/', $home->index(...));
        $r->get('/lang/{lang}', $home->lang(...));
        $r->get('/cron', $home->cron(...));

        $r->get('/create/{type}', $poll->createForm(...));
        $r->post('/create/{type}', $poll->create(...));
        $r->get('/p/{pid}', $poll->show(...));
        $r->post('/p/{pid}/vote', $poll->vote(...));
        $r->post('/p/{pid}/vote/{vid}/delete', $poll->deleteVote(...));
        $r->get('/p/{pid}/v/{vid}/{token}', $poll->claim(...));

        // Gestion d'un sondage : par le créateur (lien secret) ou par un administrateur connecté.
        foreach (['/a/{pid}/{token}', '/admin/poll/{pid}'] as $prefix) {
            $r->get($prefix, $manage->show(...));
            $r->get($prefix . '/export.csv', $manage->export(...));
            $r->post($prefix . '/vote', $manage->vote(...));
            $r->post($prefix . '/vote/{vid}/delete', $manage->deleteVote(...));
            $r->post($prefix . '/settings', $manage->settings(...));
            $r->post($prefix . '/options', $manage->options(...));
            $r->post($prefix . '/delete', $manage->delete(...));
        }

        $r->get('/admin', $admin->dashboard(...));
        $r->get('/admin/login', $admin->loginForm(...));
        $r->post('/admin/login', $admin->login(...));
        $r->post('/admin/logout', $admin->logout(...));
        $r->post('/admin/purge', $admin->purge(...));
        $r->get('/admin/users', $admin->users(...));
        $r->post('/admin/users', $admin->createUser(...));
        $r->post('/admin/users/{id}/delete', $admin->deleteUser(...));
        $r->post('/admin/password', $admin->changePassword(...));
        $r->get('/admin/setup', $admin->setupForm(...));
        $r->post('/admin/setup', $admin->setup(...));

        return $r;
    }

    /**
     * Nettoyage automatique au plus une fois par jour, déclenché par le trafic :
     * fonctionne même sans tâche planifiée configurée.
     */
    private function maybeAutoPurge(): void
    {
        $marker = $this->root . '/var/last_purge';
        if (is_file($marker) && filemtime($marker) > time() - 86400) {
            return;
        }
        if (@touch($marker) === false) {
            return;
        }
        try {
            $this->runMaintenance();
        } catch (\Throwable $e) {
            $this->log('Nettoyage automatique : ' . $e->getMessage());
        }
    }

    /** Supprime les sondages expirés et les anciennes tentatives de connexion. */
    public function runMaintenance(): int
    {
        $count = $this->polls()->purgeExpired();
        $this->admins()->purgeAttempts(date('Y-m-d H:i:s', time() - 86400));
        if ($count > 0) {
            $this->log("Nettoyage : $count sondage(s) supprimé(s)");
        }
        return $count;
    }

    public function errorPage(int $status, string $messageKey, ?string $detail = null): Response
    {
        try {
            $html = $this->view->render('error', [
                'title' => t('error.title'),
                'status' => $status,
                'message' => t($messageKey),
                'detail' => $detail,
            ]);
        } catch (\Throwable) {
            $html = '<h1>Erreur ' . $status . '</h1>';
        }
        return Response::html($html, $status);
    }

    private function withSecurityHeaders(Response $response): Response
    {
        $response->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            // Les liens d'administration contiennent un secret : ne jamais le transmettre en Referer.
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'")
            ->withHeader('Cache-Control', 'no-store, private');
        if ($this->isSecure()) {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000');
        }
        return $response;
    }
}
