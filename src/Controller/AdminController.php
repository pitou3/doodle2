<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Migrator;

/** Espace d'administration du site : modération de tous les sondages et gestion des comptes. */
final class AdminController extends Controller
{
    private const MAX_FAILURES = 10;
    private const FAILURE_WINDOW = 900; // secondes
    private const MIN_PASSWORD = 10;
    private const PER_PAGE = 50;

    // --- Connexion ----------------------------------------------------------

    public function loginForm(Request $r, array $p): Response
    {
        if ($this->app->currentAdmin() !== null) {
            return $this->redirect('/admin');
        }
        return $this->render('admin/login', [
            'title' => t('admin.login.title'),
            'setupAvailable' => $this->setupAvailable(),
        ]);
    }

    public function login(Request $r, array $p): Response
    {
        $admins = $this->app->admins();
        $ip = $r->ip();
        if ($admins->recentFailures($ip, date('Y-m-d H:i:s', time() - self::FAILURE_WINDOW)) >= self::MAX_FAILURES) {
            $this->message('error', 'admin.login.too_many');
            return $this->redirect('/admin/login');
        }

        $username = trim($r->postString('username'));
        $password = $r->postString('password');
        $admin = $username !== '' ? $admins->findByUsername($username) : null;
        // Si le compte n'existe pas, on calcule quand même un hash pour un temps de réponse comparable.
        $valid = $admin !== null ? password_verify($password, $admin['password_hash']) : (password_hash($password, PASSWORD_DEFAULT) && false);
        if ($admin === null || !$valid) {
            $admins->recordFailedLogin($ip, $username, $this->now());
            $this->message('error', 'admin.login.failed');
            return $this->redirect('/admin/login');
        }

        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $admins->updatePassword((int) $admin['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $admins->clearFailures($ip);
        $admins->touchLogin((int) $admin['id'], $this->now());
        $this->app->session->regenerate();
        $this->app->session->set('admin_id', (int) $admin['id']);
        $this->app->session->set('admin_seen', time());
        return $this->redirect('/admin');
    }

    public function logout(Request $r, array $p): Response
    {
        $this->app->session->remove('admin_id');
        $this->app->session->regenerate();
        $this->app->forgetCurrentAdmin();
        $this->message('success', 'admin.logged_out');
        return $this->redirect('/');
    }

    // --- Modération ---------------------------------------------------------

    public function dashboard(Request $r, array $p): Response
    {
        $admin = $this->requireAdmin();
        $q = trim($r->queryString('q'));
        $status = $r->queryString('status', 'all');
        if (!in_array($status, ['all', 'open', 'archived'], true)) {
            $status = 'all';
        }
        $page = max(1, (int) $r->queryString('page', '1'));
        $result = $this->app->pollRepo()->search($q, $status, $this->now(), self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->render('admin/dashboard', [
            'title' => t('admin.dashboard.title'),
            'admin' => $admin,
            'polls' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'q' => $q,
            'status' => $status,
            'service' => $this->app->polls(),
            'retention' => (int) $this->app->config['app']['retention_months'],
        ]);
    }

    public function purge(Request $r, array $p): Response
    {
        $this->requireAdmin();
        $count = $this->app->runMaintenance();
        $this->message('success', 'admin.purged', ['count' => $count]);
        return $this->redirect('/admin');
    }

    // --- Comptes ------------------------------------------------------------

    public function users(Request $r, array $p): Response
    {
        $admin = $this->requireAdmin();
        return $this->render('admin/users', [
            'title' => t('admin.users.title'),
            'admin' => $admin,
            'users' => $this->app->admins()->all(),
        ]);
    }

    public function createUser(Request $r, array $p): Response
    {
        $this->requireAdmin();
        $username = trim($r->postString('username'));
        $error = $this->validateCredentials($username, $r->postString('password'), $r->postString('password_confirm'));
        if ($error === null && $this->app->admins()->findByUsername($username) !== null) {
            $error = 'admin.users.exists';
        }
        if ($error !== null) {
            $this->message('error', $error, ['min' => self::MIN_PASSWORD]);
            return $this->redirect('/admin/users');
        }
        $this->app->admins()->create($username, password_hash($r->postString('password'), PASSWORD_DEFAULT), $this->now());
        $this->message('success', 'admin.users.created_ok', ['name' => $username]);
        return $this->redirect('/admin/users');
    }

    public function deleteUser(Request $r, array $p): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) $p['id'];
        $admins = $this->app->admins();
        if ($id === (int) $admin['id']) {
            $this->message('error', 'admin.users.cannot_delete_self');
        } elseif ($admins->findById($id) !== null) {
            $admins->delete($id);
            $this->message('success', 'admin.users.deleted');
        }
        return $this->redirect('/admin/users');
    }

    public function changePassword(Request $r, array $p): Response
    {
        $admin = $this->requireAdmin();
        if (!password_verify($r->postString('current_password'), $admin['password_hash'])) {
            $this->message('error', 'admin.password.wrong_current');
            return $this->redirect('/admin/users');
        }
        $error = $this->validateCredentials($admin['username'], $r->postString('password'), $r->postString('password_confirm'));
        if ($error !== null) {
            $this->message('error', $error, ['min' => self::MIN_PASSWORD]);
            return $this->redirect('/admin/users');
        }
        $this->app->admins()->updatePassword((int) $admin['id'], password_hash($r->postString('password'), PASSWORD_DEFAULT));
        $this->app->session->regenerate();
        $this->message('success', 'admin.password.changed');
        return $this->redirect('/admin/users');
    }

    // --- Installation -------------------------------------------------------

    public function setupForm(Request $r, array $p): Response
    {
        if ((string) $this->app->config['app']['setup_token'] === '') {
            throw new HttpException(404, 'error.not_found');
        }
        return $this->render('admin/setup', ['title' => t('admin.setup.title')]);
    }

    public function setup(Request $r, array $p): Response
    {
        $token = (string) $this->app->config['app']['setup_token'];
        if ($token === '') {
            throw new HttpException(404, 'error.not_found');
        }
        if (!hash_equals($token, $r->postString('setup_token'))) {
            $this->message('error', 'admin.setup.bad_token');
            return $this->redirect('/admin/setup');
        }

        $applied = (new Migrator($this->app->db(), $this->app->root . '/migrations'))->migrate();
        $admins = $this->app->admins();
        if ($admins->count() > 0) {
            $this->message('info', 'admin.setup.migrated', ['count' => count($applied)]);
            return $this->redirect('/admin/login');
        }

        $username = trim($r->postString('username'));
        $error = $this->validateCredentials($username, $r->postString('password'), $r->postString('password_confirm'));
        if ($error !== null) {
            $this->message('error', $error, ['min' => self::MIN_PASSWORD]);
            return $this->redirect('/admin/setup');
        }
        $admins->create($username, password_hash($r->postString('password'), PASSWORD_DEFAULT), $this->now());
        $this->message('success', 'admin.setup.done');
        return $this->redirect('/admin/login');
    }

    private function setupAvailable(): bool
    {
        if ((string) $this->app->config['app']['setup_token'] === '') {
            return false;
        }
        try {
            return $this->app->admins()->count() === 0;
        } catch (\PDOException) {
            return true; // tables pas encore créées
        }
    }

    private function validateCredentials(string $username, string $password, string $confirm): ?string
    {
        if (!preg_match('/^[\p{L}0-9._@-]{3,60}$/u', $username)) {
            return 'admin.users.invalid_username';
        }
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return 'admin.password.too_short';
        }
        if ($password !== $confirm) {
            return 'admin.password.mismatch';
        }
        return null;
    }
}
